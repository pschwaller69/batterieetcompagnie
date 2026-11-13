(function () {
  'use strict';

  // Confirmation avant soumission
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  // Actions groupées sur les contacts
  var bulk = document.getElementById('bulkform');
  if (bulk) {
    var boxes = function () { return bulk.querySelectorAll('input[name="ids[]"]'); };
    var count = function () {
      var n = bulk.querySelectorAll('input[name="ids[]"]:checked').length;
      document.getElementById('selcount').textContent = n;
    };
    bulk.addEventListener('change', function (e) {
      if (e.target.hasAttribute('data-checkall')) {
        boxes().forEach(function (b) { b.checked = e.target.checked; });
      }
      count();
    });
    bulk.addEventListener('submit', function (e) {
      var a = document.getElementById('bulkaction').value;
      var lid = bulk.querySelector('select[name=list_id]').value;
      if (!a) { e.preventDefault(); alert('Choisissez une action.'); return; }
      if ((a === 'add_list' || a === 'remove_list') && !lid) { e.preventDefault(); alert('Choisissez une liste.'); return; }
      if (a === 'delete' && !confirm('Supprimer définitivement les contacts sélectionnés ?')) { e.preventDefault(); }
    });
  }

  // Éditeur de campagne (Quill)
  window.initCampaignEditor = function () {
    if (!window.Quill) { return; }
    // Styles en ligne plutôt que classes : indispensable pour les clients mail
    ['align', 'background', 'color', 'size'].forEach(function (k) {
      Quill.register(Quill.import('attributors/style/' + k), true);
    });
    var Size = Quill.import('attributors/style/size');
    Size.whitelist = ['13px', '15px', '18px', '22px', '28px'];
    Quill.register(Size, true);

    var quill = new Quill('#editor', {
      theme: 'snow',
      placeholder: 'Rédigez votre message…',
      modules: {
        toolbar: {
          container: [
            [{ header: [1, 2, 3, false] }],
            [{ size: ['13px', false, '18px', '22px', '28px'] }],
            ['bold', 'italic', 'underline'],
            [{ color: [] }, { background: [] }],
            [{ align: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link', 'image'],
            ['clean']
          ],
          handlers: { image: uploadImage }
        }
      }
    });

    var input = document.getElementById('html-input');
    if (input.value) {
      quill.clipboard.dangerouslyPasteHTML(input.value);
    }

    function uploadImage() {
      var fi = document.createElement('input');
      fi.type = 'file';
      fi.accept = 'image/png,image/jpeg,image/gif,image/webp';
      fi.onchange = function () {
        var file = fi.files[0];
        if (!file) { return; }
        var fd = new FormData();
        fd.append('image', file);
        fd.append('_csrf', window.CSRF);
        fetch('index.php?p=upload_image', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.error) { alert(j.error); return; }
            var range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', j.url, 'user');
            quill.setSelection(range.index + 1);
          })
          .catch(function () { alert("Échec de l'envoi de l'image."); });
      };
      fi.click();
    }

    // Insertion des variables
    document.querySelectorAll('[data-insert]').forEach(function (el) {
      el.addEventListener('click', function () {
        var range = quill.getSelection(true);
        quill.insertText(range.index, el.getAttribute('data-insert'), 'user');
      });
    });

    // getSemanticHTML produit du HTML standard (vraies <ul>/<ol>) ; Quill 2.0.x y remplace
    // tous les espaces par &nbsp;, ce qui empêche le retour à la ligne : on les rétablit.
    function exportHtml() {
      var html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
      return html.replace(/&nbsp;/g, ' ').replace(/ /g, ' ');
    }

    document.getElementById('campaignform').addEventListener('submit', function () {
      input.value = quill.getText().trim() === '' && quill.root.querySelectorAll('img').length === 0 ? '' : exportHtml();
    });
  };
})();
