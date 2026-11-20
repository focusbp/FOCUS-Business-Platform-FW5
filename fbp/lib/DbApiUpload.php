<?php
/** Resumable signed-JSON attachments. All participating FFM connections are opened
 * before reading state; no private mutex or client-selected filesystem paths. */
final class DbApiUploadException extends RuntimeException {
    public string $errorCode;
    public function __construct(string $code, string $message, int $http = 400) {
        parent::__construct($message, $http); $this->errorCode = $code;
    }
}

final class DbApiUpload {
    public const CHUNK_SIZE = 1048576;
    public const MAX_SIZE = 268435456;
    public const MAX_ACTIVE = 8;
    public const MAX_RESERVED = 1073741824;
    public const IDLE_TTL = 86400;
    public const MAX_TTL = 604800;
    private $ctl;
    private $sessions;
    private $files;
    private string $owner;
    private string $root;

    public function __construct(Controller $ctl, string $owner) {
        $this->ctl = $ctl; $this->owner = $owner;
        $this->sessions = $ctl->db('uploads', 'db_api');
        $this->sessions->set_strict_field_length(true);
        $this->files = $ctl->db('file', 'upload');
        $ctl->db('db', 'db'); $ctl->db('db_fields', 'db');
        $ctl->db('settings', 'excel_grid');
        $this->root = rtrim($ctl->get_temp_dir(), '/') . '/fbp-db-api-uploads-' . substr(hash('sha256', $ctl->dirs->datadir), 0, 24);
        if (is_link($this->root)) $this->fail('unsafe_storage', 'Unsafe temporary storage.', 500);
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) $this->fail('storage_error', 'Cannot create temporary storage.', 507);
    }

    public function handle(string $operation, array $p): array {
        if ($operation === 'begin') return $this->begin($p);
        if ($operation === 'cleanup') return $this->cleanup();
        $row = $this->find($p['upload_id'] ?? null);
        $d = $this->data($row);
        // Opening the target may reorder FFM locks: never use the preliminary read.
        $target = $this->target($d);
        $row = $this->find($p['upload_id']); $d = $this->data($row);
        if ($row['state'] === 'complete') {
            if (!in_array($operation, ['status', 'commit'], true)) $this->fail('already_complete', 'Upload is already complete.', 409);
            $this->removeParts($row);
            return $this->response($row);
        }
        if (in_array($row['state'], ['aborted', 'expired'], true)) {
            if ($operation === 'status' || $operation === 'abort') return $this->response($row);
            $this->fail('upload_closed', 'Upload is aborted or expired.', 410);
        }
        if ($this->expired($row) && $row['state'] === 'receiving') {
            $row['state'] = 'expired'; $this->save($row, $d); $this->removeParts($row);
            if ($operation === 'status') return $this->response($row);
            $this->fail('upload_expired', 'Upload expired. Start with a new request_id.', 410);
        }
        if ($operation === 'status') {
            if ($row['state']==='receiving') {
                $dir=$this->root.'/'.$row['upload_id']; $changed=false;
                foreach ($d['chunks'] as $i=>$hash) {
                    $part="$dir/$i.part";
                    if (!is_file($part) || is_link($part) || !hash_equals($hash,hash_file('sha256',$part))) {
                        unset($d['chunks'][$i]); $changed=true;
                    }
                }
                // Status does not extend inactivity lifetime.
                if ($changed) { $row['data_json']=$this->encode($d); $this->sessions->update($row); }
            }
            return $this->response($row);
        }
        if ($operation === 'chunk') return $this->chunk($row, $d, $p);
        if ($operation === 'commit') return $this->commit($row, $d, $target);
        if ($operation === 'abort') {
            $current = $target->get($d['target_id']);
            if (!empty($d['file_id']) && (int)($current[$d['field']] ?? 0) === $d['file_id']) $this->fail('already_attached', 'Attachment already linked; retry commit.', 409);
            // Persist cancellation before deleting any temporary or reserved file.
            $row['state'] = 'aborted'; $this->save($row, $d);
            $this->removeReserved($row, $d); $this->removeParts($row);
            return $this->response($row);
        }
        $this->fail('invalid_operation', 'Unknown upload operation.');
    }

    private function begin(array $p): array {
        if (isset($p['dbclass']) && $p['dbclass'] !== 'common') $this->fail('invalid_target','Only common application notes are supported.');
        foreach (['request_id', 'table', 'field', 'filename', 'sha256'] as $key) {
            if (!isset($p[$key]) || !is_string($p[$key])) $this->fail('invalid_arguments', "$key must be a string.");
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $p['request_id'])) $this->fail('invalid_request_id', 'request_id must contain 16-128 safe characters.');
        $this->digest($p['sha256']);
        $size = $this->integer($p['size'] ?? null, 0, self::MAX_SIZE, 'size');
        $id = $this->integer($p['id'] ?? null, 1, PHP_INT_MAX, 'id');
        if (isset($p['replace']) && !is_bool($p['replace'])) $this->fail('invalid_arguments', 'replace must be boolean.');
        $filename = $p['filename'];
        if ($filename === '' || strlen($filename) > 255 || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $filename) || in_array($filename, ['.', '..'], true)) $this->fail('invalid_filename', 'Invalid filename.');
        $spec = ['table'=>$p['table'], 'target_id'=>$id, 'field'=>$p['field'], 'filename'=>$filename,
            'size'=>$size, 'sha256'=>$p['sha256'], 'replace'=>$p['replace'] ?? false];
        $target = $this->target($spec);
        $field = $this->field($spec);
        // All database opens and field resolution precede this state read.
        $matches = $this->sessions->select(['owner','request_id'], [$this->owner,$p['request_id']]);
        if ($matches) {
            $old = $this->data($matches[0]);
            foreach ($spec as $key=>$value) if (($old[$key] ?? null) !== $value) $this->fail('request_conflict', 'request_id is already used for a different upload.', 409);
            $previous=$matches[0];
            if ($previous['state']==='receiving' && $this->expired($previous)) {
                $previous['state']='expired'; $this->save($previous,$old); $this->removeParts($previous);
            }
            return $this->response($previous);
        }
        $record = $target->get($id);
        if (!$record) $this->fail('item_not_found', 'Target record not found.', 404);
        $before = (int)($record[$p['field']] ?? 0);
        if ($before && !$spec['replace']) $this->fail('attachment_exists', 'Attachment exists; explicit replace=true is required.', 409);
        $active = 0; $reserved = 0;
        foreach ($this->sessions->getall() as $r) {
            if (in_array($r['state'], ['receiving','committing','prepared'], true)) {
                // Expired receiving sessions release their reservations here.
                if ($r['state'] === 'receiving' && $this->expired($r)) {
                    $r['state']='expired'; $this->save($r,$this->data($r)); $this->removeParts($r); continue;
                }
                $active++; $reserved += $this->data($r)['size'];
            }
        }
        if ($active >= self::MAX_ACTIVE || $reserved + $size > self::MAX_RESERVED) $this->fail('upload_quota', 'Active upload quota exceeded.', 429);
        $spec += ['expected_file_id'=>$before, 'chunk_size'=>self::CHUNK_SIZE,
            'chunk_count'=>max(1,(int)ceil($size/self::CHUNK_SIZE)), 'chunks'=>[], 'file_id'=>0,
            'field_spec'=>$this->fieldSpec($field)];
        $row = ['upload_id'=>bin2hex(random_bytes(32)), 'owner'=>$this->owner, 'request_id'=>$p['request_id'],
            'state'=>'receiving', 'created_at'=>time(), 'updated_at'=>time(), 'data_json'=>$this->encode($spec)];
        $this->sessions->insert($row);
        return $this->response($row);
    }

    private function chunk(array $row, array $d, array $p): array {
        if ($row['state'] !== 'receiving') $this->fail('upload_finalizing', 'Upload is being finalized.', 409);
        $index = $this->integer($p['index'] ?? null, 0, $d['chunk_count']-1, 'index');
        $expected = min($d['chunk_size'], $d['size']-$index*$d['chunk_size']);
        if (($p['size'] ?? null) !== $expected) $this->fail('chunk_size_mismatch', 'Incorrect decoded chunk size.');
        $this->digest($p['sha256'] ?? null);
        $encoded = $p['content_base64'] ?? null;
        if (!is_string($encoded) || strlen($encoded) !== 4*(int)ceil($expected/3)) $this->fail('invalid_base64', 'Invalid Base64 size.');
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || base64_encode($bytes) !== $encoded || strlen($bytes) !== $expected || !hash_equals($p['sha256'], hash('sha256',$bytes))) $this->fail('chunk_hash_mismatch', 'Chunk encoding or hash mismatch.');
        $key = (string)$index;
        if (isset($d['chunks'][$key]) && $d['chunks'][$key] !== $p['sha256']) $this->fail('chunk_conflict', 'Chunk number already contains different data.', 409);
        $dir = $this->directory($row);
        $path = "$dir/$index.part";
        // A successful prior write whose response/state update was lost is safe to repeat.
        if (is_link($path)) $this->fail('unsafe_storage','Unsafe chunk path.',500);
        $tmp = tempnam($dir, 'part-');
        if ($tmp === false) $this->fail('storage_error','Cannot create chunk.',507);
        try {
            if (file_put_contents($tmp,$bytes) !== strlen($bytes) || !rename($tmp,$path)) $this->fail('storage_error','Cannot save chunk.',507);
        } finally { if (is_file($tmp)) unlink($tmp); }
        $d['chunks'][$key] = $p['sha256'];
        $this->save($row,$d);
        return $this->response($row);
    }

    private function commit(array $row, array $d, $target): array {
        $field = $this->field($d);
        if ($this->fieldSpec($field) !== $d['field_spec']) $this->fail('field_changed','Attachment field definition changed.',409);
        $record = $target->get($d['target_id']);
        if (!$record) $this->fail('item_not_found','Target record not found.',404);
        $current = (int)($record[$d['field']] ?? 0);
        if ($row['state']==='prepared') $this->verifyPrepared($row,$d);
        if ($d['file_id'] && $current === $d['file_id'] && $row['state'] === 'prepared') {
            $row['state']='complete'; $this->save($row,$d); $this->removeParts($row);
            return $this->response($row);
        }
        if ($current !== $d['expected_file_id']) $this->fail('target_changed','Target attachment changed since upload began.',409);
        if (count($d['chunks']) !== $d['chunk_count']) $this->fail('chunks_missing','Some chunks have not been received.',409);
        if ($row['state'] !== 'prepared') {
            $assembled = $this->assemble($row,$d);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($assembled);
            if ($field['type'] === 'image') {
                $image = @getimagesize($assembled);
                if (!$image || !in_array($image[2], [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_GIF],true)) $this->fail('invalid_image','Image field requires JPEG, PNG or GIF.');
                if ($image[0] > 16000 || $image[1] > 16000 || $image[0]*$image[1] > 40000000) $this->fail('image_too_large','Image dimensions exceed the processing limit.',413);
                $this->imageMemory($image,$field);
            }
            $row['state']='committing'; $this->save($row,$d);
            // The deterministic reserved path lets retry recover an insert made
            // immediately before process termination, even before file_id was saved.
            $stored = 'api_upload_' . $row['upload_id'];
            if (!$d['file_id']) {
                $found = $this->files->select('path',$stored);
                if ($found) $d['file_id']=(int)$found[0]['id'];
                else {
                    $file=['filename'=>$d['filename'],'path'=>$stored,'path_th'=>$stored.'_th',
                        'table_identifer'=>$target->get_identifier(),'row_id'=>$d['target_id']];
                    $this->files->insert($file); $d['file_id']=(int)$file['id'];
                }
                $this->save($row,$d);
            }
            $this->ctl->save_local_file($assembled,$stored);
            if ($field['type'] === 'image') {
                $width = (int)($field['image_width'] ?? 0);
                $thumb = (int)($field['image_width_thumbnail'] ?? 0);
                if ($width > 0) $this->ctl->resize_saved_image($stored,$stored,$width);
                if ($thumb > 0) $this->ctl->resize_saved_image($stored,$stored.'_th',$thumb);
            }
            $path = $this->ctl->get_saved_filepath($stored);
            $d['result']=['file_id'=>$d['file_id'],'filename'=>$d['filename'],'source_size'=>$d['size'],
                'source_sha256'=>$d['sha256'],'stored_size'=>filesize($path),'stored_sha256'=>hash_file('sha256',$path),
                'mime_type'=>(new finfo(FILEINFO_MIME_TYPE))->file($path) ?: $mime];
            $row['state']='prepared'; $this->save($row,$d);
        }
        $this->verifyPrepared($row,$d);
        // Partial update preserves other fields. FFM enforces the target's DSP.
        $target->update(['id'=>$d['target_id'],$d['field']=>$d['file_id']]);
        $linked=$target->get($d['target_id']);
        if ((int)($linked[$d['field']]??0) !== $d['file_id']) $this->fail('attach_failed','Could not confirm target attachment.',500);
        $row['state']='complete'; $this->save($row,$d); $this->removeParts($row);
        return $this->response($row);
    }

    private function verifyPrepared(array $row,array $d): void {
        $stored='api_upload_'.$row['upload_id'];
        $info=$this->files->get($d['file_id']);
        $path=$this->ctl->get_saved_filepath($stored);
        clearstatcache(true,$path);
        if (!$info || $info['path']!==$stored || !is_file($path) || is_link($path) || filesize($path)!==$d['result']['stored_size'] || !hash_equals($d['result']['stored_sha256'],hash_file('sha256',$path))) $this->fail('prepared_file_corrupt','Prepared attachment is missing or corrupt; abort this upload.',409);
    }

    private function assemble(array $row, array $d): string {
        $dir=$this->directory($row); $path="$dir/assembled";
        if (is_link($path)) $this->fail('unsafe_storage','Unsafe assembly path.',500);
        $out=fopen($path,'wb');
        if (!$out) $this->fail('storage_error','Cannot assemble file.',507);
        try {
            for ($i=0;$i<$d['chunk_count'];$i++) {
                $part="$dir/$i.part";
                if (!is_file($part) || is_link($part) || !hash_equals($d['chunks'][(string)$i]??'',hash_file('sha256',$part))) $this->fail('chunk_corrupt','Stored chunk missing or corrupt; resend it.',409);
                $in=fopen($part,'rb');
                if (!$in) $this->fail('storage_error','Cannot read chunk.',507);
                try { $n=stream_copy_to_stream($in,$out); } finally { fclose($in); }
                if ($n !== min($d['chunk_size'],$d['size']-$i*$d['chunk_size'])) $this->fail('storage_error','Incomplete file write.',507);
            }
        } finally { fclose($out); }
        if (filesize($path)!==$d['size'] || !hash_equals($d['sha256'],hash_file('sha256',$path))) $this->fail('file_hash_mismatch','Complete file hash mismatch.',409);
        return $path;
    }

    private function target(array $d) {
        if (!is_string($d['table']??null) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$d['table']) || !is_string($d['field']??null) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$d['field'])) $this->fail('invalid_target','Invalid note or field.');
        if (in_array($d['table'],['setting','uploads','file'],true)) $this->fail('invalid_target','Only application note attachment fields are supported.');
        // Only managed common notes; never arbitrary framework or class tables.
        $notes=$this->ctl->db('db','db')->select('tb_name',$d['table']);
        if (!$notes) $this->fail('note_not_found','Application note not found.',404);
        return $this->ctl->db($d['table'],'common');
    }

    private function field(array $d): array {
        foreach ($this->ctl->get_field_list($d['table']) as $f) if ($f['parameter_name']===$d['field'] && in_array($f['type'],['file','image'],true)) return $f;
        $this->fail('invalid_field','Target must be a file or image note field.');
    }
    private function fieldSpec(array $f): array {
        return ['type'=>$f['type'],'image_width'=>(int)($f['image_width']??0),'image_width_thumbnail'=>(int)($f['image_width_thumbnail']??0)];
    }
    private function imageMemory(array $image,array $field): void {
        $pixels=$image[0]*$image[1]; $largest=$pixels;
        foreach (['image_width','image_width_thumbnail'] as $name) {
            $width=(int)($field[$name]??0);
            if ($width<=0) continue;
            $height=(int)ceil($width*$image[1]/$image[0]);
            if ($width>16000 || $height>16000 || $width*$height>40000000) $this->fail('image_too_large','Configured image dimensions exceed the processing limit.',413);
            $largest=max($largest,$width*$height);
        }
        $limit=trim((string)ini_get('memory_limit'));
        if ($limit!=='' && $limit!=='-1') {
            $bytes=(int)$limit;
            $unit=strtolower(substr($limit,-1));
            if ($unit==='g') $bytes*=1073741824;
            elseif ($unit==='m') $bytes*=1048576;
            elseif ($unit==='k') $bytes*=1024;
            if (memory_get_usage(true)+($pixels+$largest)*8+16777216>$bytes) $this->fail('image_memory_limit','Image cannot be processed within the PHP memory limit.',413);
        }
    }
    private function find($id): array {
        if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D',$id)) $this->fail('invalid_upload_id','Invalid upload_id.');
        $rows=$this->sessions->select(['upload_id','owner'],[$id,$this->owner]);
        if (!$rows) $this->fail('upload_not_found','Upload not found.',404);
        return $rows[0];
    }
    private function expired(array $r): bool {
        return time() >= min((int)$r['updated_at']+self::IDLE_TTL,(int)$r['created_at']+self::MAX_TTL);
    }
    private function data(array $r): array { return json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR); }
    private function encode(array $d): string { return json_encode($d,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    private function save(array &$row,array $d): void {
        $row['data_json']=$this->encode($d); $row['updated_at']=time(); $this->sessions->update($row);
    }
    private function response(array $row): array {
        $d=$this->data($row); $received=array_map('intval',array_keys($d['chunks'])); sort($received);
        return ['ok'=>true,'upload_id'=>$row['upload_id'],'state'=>$row['state'],'table'=>$d['table'],
            'id'=>$d['target_id'],'field'=>$d['field'],'chunk_size'=>$d['chunk_size'],'chunk_count'=>$d['chunk_count'],
            'received'=>$received,'expires_at'=>min((int)$row['updated_at']+self::IDLE_TTL,(int)$row['created_at']+self::MAX_TTL),
            'result'=>$d['result']??null];
    }
    private function directory(array $row): string {
        $dir=$this->root.'/'.$row['upload_id'];
        if (is_link($dir)) $this->fail('unsafe_storage','Unsafe upload directory.',500);
        if (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) $this->fail('storage_error','Cannot create upload directory.',507);
        return $dir;
    }
    private function removeParts(array $row): void {
        $dir=$this->root.'/'.$row['upload_id'];
        if (!is_dir($dir) || is_link($dir)) return;
        foreach (scandir($dir) as $name) if ($name!=='.' && $name!=='..' && (is_file("$dir/$name") || is_link("$dir/$name"))) {
            if (!unlink("$dir/$name")) $this->fail('cleanup_failed','Temporary file cleanup failed.',500);
        }
        if (!rmdir($dir)) $this->fail('cleanup_failed','Temporary directory cleanup failed.',500);
    }
    private function removeReserved(array $row,array $d): void {
        foreach ($this->files->select('path','api_upload_'.$row['upload_id']) as $file) $this->ctl->delete_file((int)$file['id']);
    }
    private function cleanup(): array {
        $count=0; $pending=[];
        foreach ($this->sessions->getall() as $row) {
            if ($row['owner']!==$this->owner) continue;
            if (in_array($row['state'],['committing','prepared'],true)) { if ($this->expired($row)) $pending[]=$row['upload_id']; continue; }
            if ($row['state']==='receiving' && $this->expired($row)) { $row['state']='expired'; $this->save($row,$this->data($row)); $count++; }
            if (in_array($row['state'],['complete','expired','aborted'],true)) {
                $this->removeParts($row);
                if ($row['state']==='aborted') $this->removeReserved($row,$this->data($row));
                if (time()-(int)$row['updated_at']>self::MAX_TTL) $this->sessions->delete($row['id']);
            }
        }
        // A SIGKILL inside stream_copy_to_stream cannot run PHP's finally.
        // These framework-generated staging names are never attachment paths.
        $copyCount=0;
        $uploadDir=dirname($this->ctl->get_saved_filepath('placeholder'));
        foreach (glob($uploadDir.'/managed-copy-*') ?: [] as $path) {
            if (is_file($path) && !is_link($path) && filemtime($path)<time()-self::MAX_TTL) {
                if (!unlink($path)) $this->fail('cleanup_failed','Managed staging cleanup failed.',500);
                $copyCount++;
            }
        }
        return ['ok'=>true,'expired_count'=>$count,'staging_removed_count'=>$copyCount,'needs_recovery'=>$pending];
    }
    private function digest($value): void {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D',$value)) $this->fail('invalid_sha256','SHA-256 must be 64 lowercase hexadecimal characters.');
    }
    private function integer($v,int $min,int $max,string $name): int {
        if (!is_int($v) || $v<$min || $v>$max) $this->fail('invalid_'.$name,"$name is out of range."); return $v;
    }
    private function fail(string $code,string $message,int $status=400): void { throw new DbApiUploadException($code,$message,$status); }
}
