(function () {
   function openModalByTrigger(triggerId) {
      const trigger = document.getElementById(triggerId);

      if (!trigger) {
         return;
      }

      trigger.click();
   }

   function initInjectedContent(target) {
      if (typeof window.initSummernote === 'function') {
         window.initSummernote(target);
      }

      if (window.HSStaticMethods && typeof window.HSStaticMethods.autoInit === 'function') {
         window.HSStaticMethods.autoInit();
      }

      if (window.FlowbiteInstances && typeof window.initFlowbite === 'function') {
         window.initFlowbite();
      }
   }

   document.removeEventListener('click', window.globalAjaxModalHandler);

   window.globalAjaxModalHandler = async function (e) {
      const trigger = e.target.closest('[data-ajax-modal="true"]');

      if (!trigger) {
         return;
      }

      const clickedInsideBlockedElement = e.target.closest('.no-modal-click, button, a, input, select, textarea, label, svg, path');

      if (clickedInsideBlockedElement) {
         return;
      }

      const viewUrl = trigger.dataset.viewUrl;
      const modalTrigger = trigger.dataset.modalTrigger;
      const modalContent = trigger.dataset.modalContent;
      const target = document.getElementById(modalContent);

      if (!viewUrl || !modalTrigger || !target) {
         return;
      }

      target.innerHTML = `
         <div class="text-center text-gray-400 py-10">
            Loading...
         </div>
      `;

      try {
         const response = await fetch(viewUrl, {
            method: 'GET',
            headers: {
               'X-Requested-With': 'XMLHttpRequest',
               'Accept': 'text/html'
            }
         });

         if (!response.ok) {
            throw new Error('Failed to load modal content.');
         }

         target.innerHTML = await response.text();
         initInjectedContent(target);
         openModalByTrigger(modalTrigger);

      } catch (error) {
         target.innerHTML = `
            <div class="text-center text-red-500 py-10">
               Failed to load details.
            </div>
         `;

         openModalByTrigger(modalTrigger);
      }
   };

   document.addEventListener('click', window.globalAjaxModalHandler);
})();