'use strict';
window.Keycover=(()=>{
  const el=id=>document.getElementById(id);
  function lock(){}
  async function json(url){
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});
    const data=await response.json();
    if(!response.ok){if(response.status===401)lock();const err=new Error(data.error||'요청을 처리하지 못했습니다.');err.status=response.status;throw err;}
    return data;
  }
  async function download(url,filename,button,onError){
    if(button.dataset.downloading)return;
    button.dataset.downloading='1';button.setAttribute('aria-disabled','true');
    const old=button.textContent;button.textContent='다운로드 중…';
    try{
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});
      if(!response.ok){if(response.status===401)lock();const data=await response.json().catch(()=>({}));throw new Error(data.error||'표지를 다운로드하지 못했습니다.');}
      if(!response.headers.get('Content-Type')?.startsWith('image/png'))throw new Error('PNG 파일을 받지 못했습니다. 다시 시도해 주세요.');
      const blob=await response.blob();
      const bytes=new Uint8Array(await blob.slice(0,8).arrayBuffer());
      if(![137,80,78,71,13,10,26,10].every((v,i)=>bytes[i]===v))throw new Error('PNG 파일을 확인하지 못했습니다.');
      const objectUrl=URL.createObjectURL(blob),link=document.createElement('a');
      link.href=objectUrl;link.download=filename;document.body.append(link);link.click();link.remove();
      setTimeout(()=>URL.revokeObjectURL(objectUrl),60000);
    }catch(e){onError(e.message);}
    finally{button.textContent=old;button.removeAttribute('aria-disabled');delete button.dataset.downloading;}
  }
  function start(hooks){return Promise.resolve().then(()=>hooks.init()).catch(e=>hooks.error(e.message));}
  function previewColor(options={}){
    if(options.mood==='dark')return '#121B35';
    if(options.mood==='color')return /^#[0-9a-f]{6}$/i.test(options.background_color||'')?options.background_color:'#B1A1DF';
    return '#F6F5F1';
  }
  return {start,lock,json,download,previewColor};
})();
