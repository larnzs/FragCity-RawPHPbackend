(function(){
'use strict';
function initVipStorefront(){
 const root=document.getElementById('vipStorefront');
 if(!root||root.dataset.vipStoreReady==='1')return;
 root.dataset.vipStoreReady='1';
 const plans=Array.from(root.querySelectorAll('[data-vip-plan]'));
 const selectedName=root.querySelector('#vipSelectedName');
 const selectedMeta=root.querySelector('#vipSelectedMeta');
 if(!plans.length)return;
 function selectPlan(plan){
  plans.forEach(function(item){
   const selected=item===plan;
   item.classList.toggle('is-selected',selected);
   const button=item.querySelector('[data-vip-select]');
   if(button){
    button.setAttribute('aria-pressed',selected?'true':'false');
    button.innerHTML=selected?'پلن انتخاب‌شده <i class="fas fa-check" aria-hidden="true"></i>':'انتخاب این سطح <i class="fas fa-arrow-left" aria-hidden="true"></i>';
   }
  });
  if(selectedName)selectedName.textContent=plan.dataset.vipName||'VIP';
  if(selectedMeta)selectedMeta.textContent=(plan.dataset.priceLabel||'قیمت تعیین نشده')+' · '+(plan.dataset.durationLabel||'مدت اشتراک مشخص نشده');
 }
 plans.forEach(function(plan){
  const button=plan.querySelector('[data-vip-select]');
  if(button)button.addEventListener('click',function(){selectPlan(plan);});
 });
 selectPlan(plans.find(function(plan){return plan.classList.contains('is-current');})||plans[0]);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initVipStorefront,{once:true});
else initVipStorefront();
window.addEventListener('content-loaded',initVipStorefront);
})();