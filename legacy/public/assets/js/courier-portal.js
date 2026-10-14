(function(){
  'use strict';
  const qs=(s,r=document)=>r.querySelector(s);
  const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));

  function toastTimeout(){
    qsa('.courier-toast').forEach(el=>setTimeout(()=>el.classList.add('hide'),4200));
  }

  function setBusy(form,busy,text){
    const btn=qs('button[type="submit"],button:not([type])',form);
    if(!btn)return;
    if(busy){
      if(!btn.dataset.originalHtml)btn.dataset.originalHtml=btn.innerHTML;
      btn.disabled=true;btn.classList.add('is-loading');
      btn.innerHTML='<span class="courier-spinner" aria-hidden="true"></span><span>'+ (text||'Memproses...') +'</span>';
    }else{
      btn.disabled=false;btn.classList.remove('is-loading');
      if(btn.dataset.originalHtml)btn.innerHTML=btn.dataset.originalHtml;
    }
  }

  function confirmForm(form){
    const message=form.dataset.confirm;
    if(!message)return true;
    return window.confirm(message);
  }

  function submitGuard(){
    qsa('form').forEach(form=>{
      form.addEventListener('submit',e=>{
        if(form.dataset.submitting==='1'){e.preventDefault();return;}
        if(!form.checkValidity())return;
        if(!confirmForm(form)){e.preventDefault();return;}
        form.dataset.submitting='1';
        setBusy(form,true,form.dataset.loadingText||'Memproses...');
      });
    });
  }

  function passwordToggles(){
    qsa('[data-password-toggle]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const wrap=btn.closest('.courier-password-wrap');
        const input=wrap?qs('input',wrap):null;
        if(!input)return;
        const show=input.type==='password';
        input.type=show?'text':'password';
        btn.setAttribute('aria-label',show?'Sembunyikan password':'Tampilkan password');
        btn.innerHTML=show?'<i class="bi bi-eye-slash"></i>':'<i class="bi bi-eye"></i>';
      });
    });
  }

  function locationButton(){
    const btn=qs('[data-courier-location]');
    if(!btn)return;
    btn.addEventListener('click',()=>{
      const form=btn.closest('form');
      if(!form)return;
      if(!navigator.geolocation){
        alert('Browser ini tidak mendukung akses lokasi.');return;
      }
      btn.disabled=true;
      const original=btn.innerHTML;
      btn.innerHTML='<span class="courier-spinner"></span> Mengambil lokasi...';
      navigator.geolocation.getCurrentPosition(pos=>{
        const lat=qs('input[name="latitude"]',form), lng=qs('input[name="longitude"]',form);
        if(lat)lat.value=String(pos.coords.latitude);
        if(lng)lng.value=String(pos.coords.longitude);
        btn.innerHTML='<i class="bi bi-geo-alt-fill"></i> Lokasi didapat';
        form.requestSubmit();
      },err=>{
        btn.disabled=false;btn.innerHTML=original;
        const msg=err&&err.code===1?'Izin lokasi ditolak. Aktifkan izin lokasi browser lalu coba lagi.':'Lokasi belum berhasil didapat. Coba lagi di area dengan GPS/sinyal lebih baik.';
        alert(msg);
      },{enableHighAccuracy:true,timeout:12000,maximumAge:30000});
    });
  }

  function filePreview(){
    qsa('input[type="file"][name="proof"]').forEach(input=>{
      input.addEventListener('change',()=>{
        const file=input.files&&input.files[0];
        const box=qs('[data-proof-preview]');
        if(!box)return;
        if(!file){box.hidden=true;box.removeAttribute('src');return;}
        if(!/^image\//.test(file.type)){box.hidden=true;return;}
        const url=URL.createObjectURL(file);box.src=url;box.hidden=false;
        box.onload=()=>URL.revokeObjectURL(url);
      });
    });
  }

  document.addEventListener('DOMContentLoaded',()=>{
    toastTimeout();submitGuard();passwordToggles();locationButton();filePreview();
  });
})();
