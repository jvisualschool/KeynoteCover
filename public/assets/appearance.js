'use strict';
(() => {
  const key='keycover.appearance';
  const system=window.matchMedia('(prefers-color-scheme: dark)');
  const valid=value=>value==='light'||value==='dark';
  let preference=null;
  try{const saved=localStorage.getItem(key);if(valid(saved))preference=saved;}catch{}
  function apply(){
    const mode=preference||(system.matches?'dark':'light');
    document.documentElement.dataset.appearance=mode;
    const meta=document.querySelector('meta[name="theme-color"]');
    if(meta)meta.content=mode==='dark'?'#14151c':'#f6f5f1';
    const button=document.getElementById('appearance-toggle');
    if(button){
      const label=mode==='dark'?'라이트 모드로 전환':'다크 모드로 전환';
      button.setAttribute('aria-label',label);button.title=label;
    }
  }
  apply();
  document.addEventListener('DOMContentLoaded',()=>{
    apply();
    document.getElementById('appearance-toggle')?.addEventListener('click',()=>{
      preference=document.documentElement.dataset.appearance==='dark'?'light':'dark';
      try{localStorage.setItem(key,preference);}catch{}
      apply();
    });
  });
  system.addEventListener('change',()=>{if(!preference)apply();});
  window.addEventListener('storage',event=>{
    if(event.key!==key&&event.key!==null)return;
    preference=valid(event.newValue)?event.newValue:null;apply();
  });
})();
