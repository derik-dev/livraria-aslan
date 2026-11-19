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

    // Busca com sugestões enquanto digita. Enter continua indo para o catálogo.
    var forms = document.querySelectorAll('form[data-suggest-url]');
    for (var f = 0; f < forms.length; f++) {
        setupSuggest(forms[f]);
    }

    function setupSuggest(form) {
        var input = form.querySelector('input[name="q"]');
        var box = form.querySelector('.suggest');
        var status = form.querySelector('[data-suggest-status]');
        var endpoint = form.getAttribute('data-suggest-url');
        if (!input || !box || !endpoint || !window.fetch) return;

        var timer = null;
        var lastQuery = '';
        var controller = null;

        function close() {
            box.hidden = true;
            box.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
        }

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        function render(data) {
            box.innerHTML = '';
            if (!data.items.length) {
                box.appendChild(el('p', 'suggest-empty', 'Nenhum livro encontrado. Aperte Enter para ver sugestões.'));
                if (status) status.textContent = 'Nenhum livro encontrado.';
            } else {
                var list = el('ul', 'suggest-list');
                data.items.forEach(function (item) {
                    var li = el('li');
                    var a = el('a', 'suggest-item');
                    a.href = item.url;
                    var img = el('img');
                    img.src = item.image;
                    img.alt = '';
                    img.width = 48;
                    img.height = 72;
                    var info = el('span', 'suggest-info');
                    info.appendChild(el('strong', '', item.title));
                    info.appendChild(el('span', '', item.author));
                    info.appendChild(el('span', 'suggest-price', item.soldOut ? item.price + ' · Esgotado' : item.price));
                    a.appendChild(img);
                    a.appendChild(info);
                    li.appendChild(a);
                    list.appendChild(li);
                });
                box.appendChild(list);
                if (status) status.textContent = data.items.length + ' sugestões. Use as setas para escolher.';
            }
            var all = el('a', 'suggest-all', 'Ver todos os resultados para "' + data.q + '"');
            all.href = data.allUrl;
            box.appendChild(all);
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function search(q) {
            if (controller && controller.abort) controller.abort();
            controller = window.AbortController ? new AbortController() : null;
            var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q);
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', signal: controller ? controller.signal : undefined })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (data) {
                    if (input.value.trim() !== q) return; // resposta antiga, ignora
                    render(data);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    close(); // sem sugestões; o Enter continua funcionando
                });
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 2) {
                lastQuery = '';
                close();
                return;
            }
            if (q === lastQuery) return;
            timer = setTimeout(function () {
                lastQuery = q;
                search(q);
            }, 250);
        });

        // Setas navegam pelas sugestões; Esc fecha.
        form.addEventListener('keydown', function (e) {
            if (box.hidden) return;
            var links = box.querySelectorAll('a');
            if (!links.length) return;
            var index = Array.prototype.indexOf.call(links, document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                links[index < links.length - 1 ? index + 1 : 0].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (index <= 0) input.focus(); else links[index - 1].focus();
            } else if (e.key === 'Escape') {
                close();
                input.focus();
            }
        });

        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) close();
        });

        form.addEventListener('submit', function () {
            clearTimeout(timer);
        });
    }

    // Máscara simples de CEP: 00000-000
    var cep = document.getElementById('cep');
    if (cep) {
        cep.addEventListener('input', function () {
            var d = cep.value.replace(/\D/g, '').slice(0, 8);
            cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        });
    }

    // Formata centavos como no PHP: 1.234,50 €
    function money(cents) {
        var parts = (Math.max(0, cents) / 100).toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts[0] + ',' + parts[1] + ' €';
    }

    // Contador de caracteres da dedicatória (conta como o servidor: por caractere).
    document.querySelectorAll('textarea[data-counter]').forEach(function (area) {
        var out = document.getElementById(area.getAttribute('data-counter'));
        if (!out) return;
        var max = parseInt(out.getAttribute('data-max'), 10) || 0;
        function update() {
            var len = Array.from(area.value).length;
            out.textContent = len + ' de ' + max + ' caracteres';
            out.classList.toggle('counter-full', max > 0 && len >= max);
        }
        area.addEventListener('input', update);
        update();
    });

    // Barra de frete grátis no carrinho: só visual. O frete real é recalculado no servidor.
    var liveBar = document.querySelector('.free-bar[data-free-live]');
    var qtyInputs = document.querySelectorAll('.qty-input[data-price]');
    if (liveBar && qtyInputs.length) {
        var freeFrom = parseInt(liveBar.getAttribute('data-free-from'), 10) || 0;
        var text = liveBar.querySelector('.free-bar-text');
        var fill = liveBar.querySelector('.free-bar-fill');
        var updateBar = function () {
            var subtotal = 0;
            qtyInputs.forEach(function (input) {
                var qty = parseInt(input.value, 10);
                var price = parseInt(input.getAttribute('data-price'), 10) || 0;
                if (qty > 0) subtotal += qty * price;
            });
            var missing = Math.max(0, freeFrom - subtotal);
            text.textContent = '';
            var strong = document.createElement('strong');
            if (missing > 0) {
                strong.textContent = money(missing);
                text.appendChild(document.createTextNode('Faltam '));
                text.appendChild(strong);
                text.appendChild(document.createTextNode(' para frete grátis'));
            } else {
                strong.textContent = 'Você ganhou frete grátis';
                text.appendChild(strong);
            }
            fill.style.width = (freeFrom > 0 ? Math.min(100, Math.floor(subtotal * 100 / freeFrom)) : 0) + '%';
        };
        qtyInputs.forEach(function (input) { input.addEventListener('input', updateBar); });
    }

    // Botão de imprimir (dedicatórias a preparar)
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });
})();
