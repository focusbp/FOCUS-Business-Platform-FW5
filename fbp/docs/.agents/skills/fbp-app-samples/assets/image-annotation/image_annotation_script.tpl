<script>{literal}
(() => {
 'use strict';
 if (window.FBPImageAnnotation) return;
 const NS='http://www.w3.org/2000/svg';
 const svg=(tag,attrs={})=>{const e=document.createElementNS(NS,tag);for(const [k,v] of Object.entries(attrs))e.setAttribute(k,v);return e;};
 function normalize(items){
  if(!Array.isArray(items)||items.length>60)throw Error('赤字は60個までです。');
  return items.map(a=>{
   if(!a||!['arrow','rect','text'].includes(a.type))throw Error('赤字の形式を確認してください。');
   const out={type:a.type};for(const key of ['x','y','x2','y2']){const n=a[key];if(typeof n!=='number'||!Number.isFinite(n)||n<0||n>1000)throw Error('赤字の位置を確認してください。');out[key]=Math.round(n*100)/100;}
   if(a.type==='text'&&typeof a.text!=='string')throw Error('文字の形式を確認してください。');
   out.text=a.type==='text'?a.text.trim():'';
   if(a.type==='text'&&(!out.text||Array.from(out.text).length>80))throw Error('文字は1〜80文字で入力してください。');
   return out;
  });
 }
 async function mount(root,options={}){
  if(!(root instanceof HTMLElement)||!root.isConnected)throw Error('表示中の編集領域を指定してください。');
  if(!(options.image instanceof Blob)||!['image/png','image/jpeg','image/webp'].includes(options.image.type)||!options.image.size||options.image.size>12*1024*1024)throw Error('PNG・JPEG・WebP画像（12MB以内）を指定してください。');
  root.imageAnnotation?.destroy();
  const q=name=>root.querySelector(`[data-ia="${name}"]`),image=q('image'),overlay=q('overlay'),inline=q('inline'),stage=q('stage'),abort=new AbortController();
  const listen=(el,name,fn)=>el.addEventListener(name,fn,{signal:abort.signal});
  let marks=[],history=[],selected=-1,tool='arrow',start=null,preview=null,drag=null,textEdit=null,busy=false,disposed=false,resize=null;
  const original=options.image,originalUrl=URL.createObjectURL(original);
  q('error').hidden=true;q('error').textContent='';q('status').textContent='';
  root.querySelectorAll('button').forEach(b=>b.disabled=true);
  function showError(error){q('error').textContent=error?.message||String(error);q('error').hidden=false;}
  function draw(target,list,interactive=false){
   const ratio=image.naturalHeight/image.naturalWidth;target.replaceChildren();target.setAttribute('viewBox',`0 0 1000 ${1000*ratio}`);
   list.forEach((a,index)=>{
    const x=a.x,y=a.y*ratio,x2=a.x2,y2=a.y2*ratio,g=svg('g'),style={stroke:'#df2929','stroke-width':4,fill:'none','stroke-linecap':'round'};target.append(g);
    if(interactive)g.dataset.markIndex=index;
    if(a.type==='rect')g.append(svg('rect',{...style,x:Math.min(x,x2),y:Math.min(y,y2),width:Math.abs(x2-x),height:Math.abs(y2-y)}));
    if(a.type==='arrow'){const angle=Math.atan2(y2-y,x2-x);g.append(svg('path',{...style,d:`M${x} ${y}L${x2} ${y2}M${x2-18*Math.cos(angle-.5)} ${y2-18*Math.sin(angle-.5)}L${x2} ${y2}L${x2-18*Math.cos(angle+.5)} ${y2-18*Math.sin(angle+.5)}`}));}
    if(a.type==='text'){const t=svg('text',{x,y,fill:'#df2929',stroke:'white','stroke-width':2,'paint-order':'stroke','font-size':24,'font-family':'sans-serif'});t.textContent=a.text;g.append(t);}
    if(interactive){
     const shape=g.firstChild;
     if(a.type==='arrow'){const hit=shape.cloneNode();hit.setAttribute('stroke','transparent');hit.setAttribute('stroke-width',22);hit.setAttribute('pointer-events','stroke');g.prepend(hit);}
     else if(a.type==='rect')shape.setAttribute('pointer-events','all');
     else{const b=shape.getBBox();g.prepend(svg('rect',{x:b.x-5,y:b.y-4,width:Math.max(24,b.width+10),height:Math.max(28,b.height+8),fill:'transparent','pointer-events':'all'}));}
     if(index===selected){const b=g.getBBox(),ring=svg('rect',{x:b.x-6,y:b.y-6,width:b.width+12,height:b.height+12,fill:'none',stroke:'#3275d5','stroke-width':1.5,'stroke-dasharray':'6 4','pointer-events':'none'});ring.dataset.selection='true';g.append(ring);}
    }
   });
  }
  function render(){
   if(disposed||!image.naturalWidth)return;
   draw(overlay,preview?[...marks,preview]:marks,true);
   root.querySelectorAll('button').forEach(b=>b.disabled=busy);
   q('undo').disabled=busy||!history.length;q('delete').disabled=busy||selected<0||selected>=marks.length;
   root.setAttribute('aria-busy',String(busy));
  }
  function remember(before=marks){history.push(structuredClone(before));if(history.length>100)history.shift();}
  function finishText(cancel=false){
   if(!textEdit)return;const edit=textEdit;textEdit=null;
   const value=Array.from(inline.textContent.replace(/[\r\n]+/g,' ')).slice(0,80).join('').trim();inline.hidden=true;inline.textContent='';
   if(!cancel){const before=structuredClone(marks);if(value){const a={...edit.annotation,text:value};if(edit.index<0){marks.push(a);selected=marks.length-1;}else marks[edit.index]=a;}else if(edit.index>=0){marks.splice(edit.index,1);selected=-1;}if(JSON.stringify(before)!==JSON.stringify(marks))remember(before);}
   render();
  }
  function editText(index,point){
   finishText();if(index<0&&marks.length>=60)return;
   const annotation=index>=0?{...marks[index]}:{type:'text',x:point.x,y:point.y,x2:point.x,y2:point.y,text:''};selected=index;textEdit={index,annotation};render();
   const width=stage.clientWidth;inline.textContent=annotation.text;inline.style.left=Math.min(annotation.x/10,92)+'%';inline.style.top=annotation.y/10+'%';inline.style.fontSize=Math.max(14,width*24/1000)+'px';inline.style.maxWidth=Math.max(40,width*(1-Math.min(annotation.x/1000,.92)))+'px';inline.hidden=false;inline.focus();
   const range=document.createRange();range.selectNodeContents(inline);const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);
  }
  function setTool(next){finishText();tool=next;overlay.dataset.tool=tool;root.querySelectorAll('[data-ia-tool]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.iaTool===tool)));}
  const position=e=>{const r=overlay.getBoundingClientRect();return {x:Math.max(0,Math.min(1000,(e.clientX-r.left)/r.width*1000)),y:Math.max(0,Math.min(1000,(e.clientY-r.top)/r.height*1000))};};
  const hit=e=>{const node=e.target.closest('[data-mark-index]');return node?Number(node.dataset.markIndex):-1;};
  function pointerMove(e){
   if(!start||busy||disposed)return;const p=position(e);
   if(drag){const a=drag.annotation,xs=a.type==='text'?[a.x]:[a.x,a.x2],ys=a.type==='text'?[a.y]:[a.y,a.y2],dx=Math.max(-Math.min(...xs),Math.min(1000-Math.max(...xs),p.x-start.x)),dy=Math.max(-Math.min(...ys),Math.min(1000-Math.max(...ys),p.y-start.y));marks[drag.index]={...a,x:a.x+dx,y:a.y+dy,x2:a.type==='text'?a.x+dx:a.x2+dx,y2:a.type==='text'?a.y+dy:a.y2+dy};}
   else preview={type:tool,...start,x2:p.x,y2:p.y,text:''};render();
  }
  root.querySelectorAll('[data-ia-tool]').forEach(b=>listen(b,'click',()=>setTool(b.dataset.iaTool)));
  listen(overlay,'pointerdown',e=>{
   if(busy||disposed||e.button!==0)return;const index=hit(e),point=position(e);
   if(tool==='text'){e.preventDefault();editText(index>=0&&marks[index].type==='text'?index:-1,point);return;}
   finishText();e.preventDefault();selected=index;start=point;drag=null;
   if(tool==='move'){if(index<0){start=null;render();return;}drag={index,before:structuredClone(marks),annotation:{...marks[index]}};}
   else if(marks.length>=60){start=null;return;}
   overlay.setPointerCapture(e.pointerId);render();
  });
  listen(overlay,'pointermove',pointerMove);
  listen(overlay,'pointerup',e=>{if(!start)return;pointerMove(e);if(drag){if(JSON.stringify(drag.before)!==JSON.stringify(marks))remember(drag.before);}else if(preview&&(Math.abs(preview.x2-preview.x)+Math.abs(preview.y2-preview.y)>2)){remember();marks.push(preview);selected=marks.length-1;}start=null;drag=null;preview=null;if(overlay.hasPointerCapture(e.pointerId))overlay.releasePointerCapture(e.pointerId);render();});
  listen(overlay,'pointercancel',()=>{if(drag)marks=drag.before;start=null;drag=null;preview=null;render();});
  listen(overlay,'dblclick',e=>{const index=hit(e);if(!busy&&index>=0&&marks[index].type==='text'){e.preventDefault();editText(index,position(e));}});
  listen(inline,'blur',()=>finishText());
  listen(inline,'keydown',e=>{if(e.isComposing)return;if(e.key==='Enter'){e.preventDefault();finishText();}if(e.key==='Escape'){e.preventDefault();e.stopPropagation();finishText(true);}});
  listen(inline,'paste',e=>{e.preventDefault();document.execCommand('insertText',false,(e.clipboardData.getData('text/plain')||'').replace(/[\r\n]+/g,' '));});
  listen(q('undo'),'click',()=>{finishText();if(history.length)marks=history.pop();selected=-1;render();});
  listen(q('delete'),'click',()=>{finishText();if(selected>=0&&selected<marks.length){remember();marks.splice(selected,1);selected=-1;render();}});
  async function getResult(){
   if(disposed)throw Error('編集画面は閉じられています。');if(!image.naturalWidth)throw Error('画像を読み込み中です。');finishText();
   const document={schema_version:1,coordinate_space:'normalized-1000',image:{width:image.naturalWidth,height:image.naturalHeight},annotations:normalize(marks)};
   const canvas=window.document.createElement('canvas');canvas.width=image.naturalWidth;canvas.height=image.naturalHeight;
   const context=canvas.getContext('2d');if(!context)throw Error('画像を作成できません。');context.drawImage(image,0,0);
   const layer=svg('svg',{xmlns:NS,width:canvas.width,height:canvas.height});draw(layer,document.annotations);
   const objectUrl=URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(layer)],{type:'image/svg+xml'}));
   try{const annotation=new Image();annotation.src=objectUrl;await annotation.decode();if(disposed)throw Error('編集画面は閉じられています。');context.drawImage(annotation,0,0,canvas.width,canvas.height);const annotatedImage=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!annotatedImage)throw Error('PNGを作成できません。');return {originalImage:original,annotatedImage,document,json:JSON.stringify(document)};}finally{URL.revokeObjectURL(objectUrl);}
  }
  function destroy(){if(disposed)return;finishText(true);disposed=true;abort.abort();resize?.disconnect();URL.revokeObjectURL(originalUrl);image.removeAttribute('src');overlay.replaceChildren();delete root.imageAnnotation;}
  const api={getResult,destroy};root.imageAnnotation=api;
  listen(q('submit'),'click',async()=>{if(busy||disposed)return;q('error').hidden=true;q('status').textContent='';busy=true;render();try{const result=await getResult();if(disposed)return;if(typeof options.onSubmit==='function')await options.onSubmit(result,api);else root.dispatchEvent(new CustomEvent('image-annotation:submit',{bubbles:true,detail:result}));if(!disposed)q('status').textContent='編集結果を取得しました。';}catch(e){if(!disposed)showError(e);}finally{busy=false;render();}});
  // The standard FBP close UI disposes transient editor data without a confirmation.
  const dialog=root.closest('.multi_dialog');if(dialog)listen(dialog,'click',e=>{if(e.target.closest('.multi_dialog_close'))destroy();});
  try{
   image.src=originalUrl;await image.decode();if(disposed)throw Error('編集画面は閉じられています。');
   if(image.naturalWidth*image.naturalHeight>16000000)throw Error('画像は1600万画素以内にしてください。');
   const input=options.annotations??[];
   if(!Array.isArray(input)){
    if(!input||input.schema_version!==1||input.coordinate_space!=='normalized-1000'||input.image?.width!==image.naturalWidth||input.image?.height!==image.naturalHeight)throw Error('注釈の版・元画像のサイズを確認してください。');
   }
   marks=normalize(Array.isArray(input)?input:input.annotations);setTool('arrow');render();
   resize=new ResizeObserver(()=>render());resize.observe(stage);return api;
  }catch(e){destroy();showError(e);throw e;}
 }
 function toFormData(result){const data=new FormData();data.append('original_image',result.originalImage,'original.'+({'image/png':'png','image/jpeg':'jpg','image/webp':'webp'}[result.originalImage.type]||'bin'));data.append('annotated_image',result.annotatedImage,'annotated.png');data.append('annotations_json',result.json);return data;}
 window.FBPImageAnnotation={mount,toFormData,version:1};
})();
{/literal}</script>
