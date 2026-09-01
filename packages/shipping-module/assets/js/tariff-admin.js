(function () {
    function markDirty() {
        var save = document.getElementById('oxidshipping-save');
        if (save) {
            save.disabled = false;
        }
    }

    function namePrefix(tbody) {
        var prefix = tbody.getAttribute('data-name-prefix');
        if (prefix) {
            return prefix;
        }
        var table = tbody.getAttribute('data-oxidshipping-floors');
        if (table) {
            return 'classification[' + table + ']';
        }
        return '';
    }

    function fieldSuffix(el) {
        var field = el.getAttribute('data-name');
        if (field) {
            return field;
        }
        var match = (el.name || '').match(/\[([^\]]+)\]$/);
        return match ? match[1] : '';
    }

    function applyName(el, prefix, index) {
        var field = fieldSuffix(el);
        if (!field) {
            return;
        }
        if (field.charAt(0) === '[') {
            el.name = prefix + '[' + index + ']' + field;
            return;
        }
        el.name = prefix + '[' + index + '][' + field + ']';
    }

    function reindex(tbody) {
        var prefix = namePrefix(tbody);
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var inputs = rows[i].querySelectorAll('input, select');
            for (var j = 0; j < inputs.length; j++) {
                applyName(inputs[j], prefix, i);
            }
        }
    }

    function syncForbidden(row) {
        var box = row.querySelector('.oxidshipping-forbidden');
        if (!box) {
            return;
        }
        var days = row.querySelectorAll('.oxidshipping-day');
        for (var i = 0; i < days.length; i++) {
            days[i].disabled = box.checked;
        }
    }

    function collectedZoneIds() {
        var ids = [];
        var inputs = document.querySelectorAll('[data-oxidshipping-rows="definitions"] input[data-name="zoneId"]');
        for (var i = 0; i < inputs.length; i++) {
            var value = (inputs[i].value || '').trim();
            if (value !== '' && ids.indexOf(value) === -1) {
                ids.push(value);
            }
        }
        return ids;
    }

    function refreshPostalZoneSelects() {
        var ids = collectedZoneIds();
        var selects = document.querySelectorAll('[data-oxidshipping-rows="postal"] select[data-name="zoneId"]');
        for (var s = 0; s < selects.length; s++) {
            var select = selects[s];
            var current = select.value;
            select.innerHTML = '';
            for (var i = 0; i < ids.length; i++) {
                var option = document.createElement('option');
                option.value = ids[i];
                option.textContent = ids[i];
                if (ids[i] === current) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
        }
    }

    function canRemove(tbody) {
        var count = tbody.querySelectorAll('tr').length;
        if (tbody.hasAttribute('data-oxidshipping-floors')) {
            return count >= 2;
        }
        return count >= 1;
    }

    function bindRow(row) {
        var remove = row.querySelector('.oxidshipping-remove-row');
        if (remove) {
            remove.addEventListener('click', function () {
                var tbody = row.parentNode;
                if (!tbody || !canRemove(tbody)) {
                    return;
                }
                tbody.removeChild(row);
                reindex(tbody);
                refreshPostalZoneSelects();
                markDirty();
            });
        }
        var forbidden = row.querySelector('.oxidshipping-forbidden');
        if (forbidden) {
            forbidden.addEventListener('change', function () {
                syncForbidden(row);
                markDirty();
            });
            syncForbidden(row);
        }
        var zoneId = row.querySelector('input[data-name="zoneId"]');
        if (zoneId) {
            zoneId.addEventListener('input', refreshPostalZoneSelects);
            zoneId.addEventListener('change', refreshPostalZoneSelects);
        }
        var fields = row.querySelectorAll('input, select');
        for (var i = 0; i < fields.length; i++) {
            fields[i].addEventListener('input', markDirty);
            fields[i].addEventListener('change', markDirty);
        }
    }

    function findBody(button) {
        var table = button.getAttribute('data-oxidshipping-table');
        if (table) {
            return document.querySelector('[data-oxidshipping-floors="' + table + '"]');
        }
        var rowsKey = button.getAttribute('data-oxidshipping-rows');
        if (rowsKey) {
            return document.querySelector('[data-oxidshipping-rows="' + rowsKey + '"]');
        }
        return null;
    }

    function findTemplate(button) {
        var templateId = button.getAttribute('data-oxidshipping-template');
        if (templateId) {
            return document.getElementById(templateId);
        }
        if (button.getAttribute('data-oxidshipping-table')) {
            return document.getElementById('oxidshipping-floor-template');
        }
        return null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var save = document.getElementById('oxidshipping-save');
        if (save) {
            save.disabled = true;
        }

        var bodies = document.querySelectorAll('[data-oxidshipping-floors], [data-oxidshipping-rows]');
        for (var b = 0; b < bodies.length; b++) {
            var rows = bodies[b].querySelectorAll('tr');
            for (var r = 0; r < rows.length; r++) {
                bindRow(rows[r]);
            }
        }

        var versionFields = document.querySelectorAll(
            'input[name="version"], input[name="dimFactorCmKg"], input[name="orderWeightSpeditionGrams"], input[name="servedCountries[]"]'
        );
        for (var v = 0; v < versionFields.length; v++) {
            versionFields[v].addEventListener('input', markDirty);
            versionFields[v].addEventListener('change', markDirty);
        }

        var addButtons = document.querySelectorAll('.oxidshipping-add-row');
        for (var a = 0; a < addButtons.length; a++) {
            addButtons[a].addEventListener('click', function () {
                var tbody = findBody(this);
                var template = findTemplate(this);
                if (!tbody || !template) {
                    return;
                }
                var max = parseInt(tbody.getAttribute('data-oxidshipping-max') || '16', 10);
                if (tbody.querySelectorAll('tr').length >= max) {
                    return;
                }
                var clone = template.content.firstElementChild.cloneNode(true);
                tbody.appendChild(clone);
                reindex(tbody);
                bindRow(clone);
                refreshPostalZoneSelects();
                markDirty();
            });
        }

        refreshPostalZoneSelects();

        var surchargeList = document.getElementById('oxidshipping-surcharge-list');
        var surchargeOrder = document.getElementById('oxidshipping-surcharge-order');
        if (surchargeList && surchargeOrder && window.jQuery && jQuery.fn.sortable) {
            function writeSurchargeOrder() {
                var ids = [];
                var items = surchargeList.querySelectorAll('[data-surcharge-id]');
                for (var i = 0; i < items.length; i++) {
                    ids.push(items[i].getAttribute('data-surcharge-id'));
                }
                surchargeOrder.value = ids.join(',');
            }
            jQuery(surchargeList).sortable({
                axis: 'y',
                opacity: 0.5,
                update: function () {
                    writeSurchargeOrder();
                    markDirty();
                }
            });
            var centsFields = surchargeList.querySelectorAll('input');
            for (var c = 0; c < centsFields.length; c++) {
                centsFields[c].addEventListener('input', markDirty);
            }
        }
    });
})();
