(function () {
    'use strict';

    // Confirmação antes de ações destrutivas (excluir livro, cancelar pedido).
    // data-confirm-select="valor": só pergunta quando o <select> do form estiver nesse valor.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        var message = form.getAttribute('data-confirm');
        if (message) {
            var onlyFor = form.getAttribute('data-confirm-select');
            var select = onlyFor ? form.querySelector('select') : null;
            var shouldAsk = !onlyFor || (select && select.value === onlyFor);
            if (shouldAsk && !window.confirm(message)) {
                event.preventDefault();
                return;
            }
        }

        // Evita pedido duplicado com clique duplo no botão.
        if (form.hasAttribute('data-once')) {
            if (form.getAttribute('data-sent') === '1') {
                event.preventDefault();
                return;
            }
            form.setAttribute('data-sent', '1');
            var buttons = form.querySelectorAll('button[type="submit"]');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].textContent = 'Enviando...';
            }
        }
    });

    // Máscara simples de CEP: 00000-000
    var cep = document.getElementById('cep');
    if (cep) {
        cep.addEventListener('input', function () {
            var d = cep.value.replace(/\D/g, '').slice(0, 8);
            cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        });
    }
})();
