<?php
/** Validate untrusted annotation JSON against the independently decoded source image. No storage. */
final class ImageAnnotationData {
    public static function validate(string $json, int $width, int $height): array {
        if(strlen($json)>40000||$width<1||$height<1||$width*$height>16000000)throw new InvalidArgumentException('画像・注釈のサイズを確認してください。');
        $value=json_decode($json,true,12,JSON_THROW_ON_ERROR);
        if(!is_array($value)||($value['schema_version']??null)!==1||($value['coordinate_space']??null)!=='normalized-1000'||($value['image']['width']??null)!==$width||($value['image']['height']??null)!==$height)throw new InvalidArgumentException('注釈の版・元画像を確認してください。');
        $items=$value['annotations']??null;
        if(!is_array($items)||!array_is_list($items)||count($items)>60)throw new InvalidArgumentException('赤字は60個までです。');
        $out=[];
        foreach($items as $item){
            if(!is_array($item)||!in_array($item['type']??null,['arrow','rect','text'],true))throw new InvalidArgumentException('赤字の種類を確認してください。');
            $annotation=['type'=>$item['type']];
            foreach(['x','y','x2','y2'] as $key){$n=$item[$key]??null;if((!is_int($n)&&!is_float($n))||!is_finite((float)$n)||$n<0||$n>1000)throw new InvalidArgumentException('赤字の位置を確認してください。');$annotation[$key]=round($n,2);}
            $text=$item['text']??'';if(!is_string($text)||!mb_check_encoding($text,'UTF-8'))throw new InvalidArgumentException('赤字の文字を確認してください。');
            $text=$annotation['type']==='text'?trim($text):'';
            if($annotation['type']==='text'&&($text===''||mb_strlen($text)>80))throw new InvalidArgumentException('文字は1〜80文字で入力してください。');
            $annotation['text']=$text;$out[]=$annotation;
        }
        return ['schema_version'=>1,'coordinate_space'=>'normalized-1000','image'=>['width'=>$width,'height'=>$height],'annotations'=>$out];
    }
}
