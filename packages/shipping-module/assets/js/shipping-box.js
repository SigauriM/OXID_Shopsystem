(function () {
    'use strict';

    var box = document.getElementById('oxidshipping-box');
    if (!box) {
        return;
    }

    var result = document.getElementById('oxidshipping-box-result');
    var plzInput = document.getElementById('oxidshipping-plz');
    var amInput = document.getElementById('oxidshipping-am');
    var button = document.getElementById('oxidshipping-calculate');
    if (!result || !plzInput || !button) {
        return;
    }

    function quote() {
        var widget = box.getAttribute('data-widget') || '/widget.php';
        var url = new URL(widget, window.location.origin);
        url.searchParams.set('cl', 'oxwshippingbox');
        url.searchParams.set('anid', box.getAttribute('data-anid') || '');
        url.searchParams.set('plz', (plzInput.value || '').trim());
        url.searchParams.set('am', amInput ? (amInput.value || '').trim() || '1' : '1');

        var request = new XMLHttpRequest();
        request.open('GET', url.toString(), true);
        request.onload = function () {
            if (this.status >= 200 && this.status < 400) {
                result.innerHTML = this.response;
            }
        };
        request.send();
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        quote();
    });

    function onEnter(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            quote();
        }
    }

    plzInput.addEventListener('keydown', onEnter);
    if (amInput) {
        amInput.addEventListener('keydown', onEnter);
    }
})();
