/* Host Seeystems — admin (ES5 + jQuery). Datos de PHP en window.HSY_ADMIN. */
jQuery(function ($) {
    var $page = $('.hsy-page');
    if (!$page.length) {
        return;
    }
    var D = window.HSY_ADMIN || {};
    var rows = [];
    var busy = false;

    // Al cambiar de pestaña se guarda cuál es, para volver a ella después de guardar.
    $page.on('click', '.nav-tab', function () {
        $('#hsy-tab').val($(this).data('tab'));
    });

    // ------------------------------------------------------------------ utilidades

    function hsy_post(action, data) {
        return $.ajax({
            url: D.ajax,
            method: 'POST',
            dataType: 'json',
            data: $.extend({ action: 'hsy_' + action, nonce: D.nonce }, data)
        }).then(function (res) {
            if (!res || !res.success) {
                return $.Deferred().reject(res && res.data ? res.data : 'Error inesperado.').promise();
            }
            return res.data;
        }, function (xhr) {
            return $.Deferred().reject('Error de conexión (' + xhr.status + ').').promise();
        });
    }

    function hsy_message(selector, text, type) {
        $(selector).html(text ? '<p class="fwue-message hsy-message ' + type + '"></p>' : '')
            .find('p').text(text);
    }

    function hsy_price(value) {
        if (value === null || value === '' || typeof value === 'undefined') {
            return '—';
        }
        var parts = Number(value).toFixed(D.decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, D.thousand);
        return D.currency + ' ' + parts.join(D.decimal);
    }

    function hsy_text(value) {
        return $('<div>').text(value === null || typeof value === 'undefined' ? '' : String(value)).html();
    }

    function hsy_busy(value) {
        busy = value;
        $page.find('#hsy-load, #hsy-import-selected, #hsy-import-all, .hsy-import-one').prop('disabled', value);
        $('#hsy-spinner').toggleClass('is-active', value);
        if (!value) {
            hsy_update_count();
        }
    }

    // ------------------------------------------------------------------ conexión

    $page.on('click', '#hsy-test', function () {
        var $btn = $(this).prop('disabled', true);
        $('#hsy-test-spinner').addClass('is-active');
        hsy_message('#hsy-test-message', '', '');
        hsy_post('test', {}).then(function (text) {
            hsy_message('#hsy-test-message', text, 'ok');
        }, function (error) {
            hsy_message('#hsy-test-message', error, 'error');
        }).always(function () {
            $btn.prop('disabled', false);
            $('#hsy-test-spinner').removeClass('is-active');
        });
    });

    // ------------------------------------------------------------------ tabla

    function hsy_status(row) {
        if (row.product_id) {
            return '<a href="' + hsy_text(row.edit) + '" target="_blank">Importado #' + row.product_id + '</a>';
        }
        return '<span class="hsy-muted">Sin importar</span>';
    }

    function hsy_row_html(row) {
        return '<tr data-id="' + hsy_text(row.id) + '">' +
            '<th scope="row" class="check-column"><input type="checkbox" class="hsy-check" value="' + hsy_text(row.id) + '"></th>' +
            '<td class="hsy-col-product"><div class="hsy-product">' +
            (row.image ? '<img src="' + hsy_text(row.image) + '" alt="" loading="lazy">' : '<span class="hsy-noimg"></span>') +
            '<div><strong>' + hsy_text(row.name) + '</strong><br><span class="hsy-muted">' + hsy_text(row.brand) + '</span></div>' +
            '</div></td>' +
            '<td>' + hsy_text(row.sku) + '</td>' +
            '<td>' + hsy_price(row.pvp) + '</td>' +
            '<td>' + hsy_price(row.cost) + '</td>' +
            '<td>' + (row.stock === null ? '—' : hsy_text(row.stock)) +
            (row.availability ? '<br><span class="hsy-muted">' + hsy_text(row.availability) + '</span>' : '') + '</td>' +
            '<td class="hsy-col-status">' + hsy_status(row) + '</td>' +
            '<td class="hsy-col-action"><button type="button" class="button button-small hsy-import-one">' +
            (row.product_id ? 'Actualizar' : 'Importar') + '</button></td>' +
            '</tr>';
    }

    function hsy_visible() {
        var q = $.trim($('#hsy-search').val() || '').toLowerCase();
        var filter = $('#hsy-filter').val();
        return $.grep(rows, function (row) {
            if (filter === 'new' && row.product_id) {
                return false;
            }
            if (filter === 'imported' && !row.product_id) {
                return false;
            }
            return !q || (row.name + ' ' + row.sku + ' ' + row.brand + ' ' + row.availability).toLowerCase().indexOf(q) !== -1;
        });
    }

    function hsy_render() {
        var visible = hsy_visible();
        var html = [];
        for (var i = 0; i < visible.length; i++) {
            html.push(hsy_row_html(visible[i]));
        }
        $('#hsy-rows').html(html.length ? html.join('') : '<tr><td colspan="8">No hay productos.</td></tr>');
        $('#hsy-total').text(visible.length);
        $page.find('.hsy-actions, .hsy-table').prop('hidden', !rows.length);
        $page.find('.hsy-check-all').prop('checked', false);
        hsy_update_count();
    }

    function hsy_selected_ids() {
        return $page.find('.hsy-check:checked').map(function () {
            return this.value;
        }).get();
    }

    function hsy_update_count() {
        var n = hsy_selected_ids().length;
        $('#hsy-selected-count').text(n);
        $('#hsy-import-selected').prop('disabled', busy || !n);
    }

    function hsy_set_rows(data) {
        rows = data.rows || [];
        $('#hsy-fetched').text(data.fetched_at ? 'Última lectura: ' + data.fetched_at : '');
        hsy_render();
    }

    $page.on('change', '.hsy-check-all', function () {
        $page.find('.hsy-check').prop('checked', this.checked);
        hsy_update_count();
    });
    $page.on('change', '.hsy-check', hsy_update_count);
    $page.on('input', '#hsy-search', hsy_render);
    $page.on('change', '#hsy-filter', hsy_render);

    $page.on('click', '#hsy-load', function () {
        if (busy) {
            return;
        }
        hsy_busy(true);
        hsy_message('#hsy-message', 'Leyendo el catálogo…', 'info');
        hsy_post('load', {}).then(function (data) {
            hsy_set_rows(data);
            hsy_message('#hsy-message', rows.length + ' productos en el catálogo.', 'ok');
        }, function (error) {
            hsy_message('#hsy-message', error, 'error');
        }).always(function () {
            hsy_busy(false);
        });
    });

    // ------------------------------------------------------------------ importar

    function hsy_progress(done, total) {
        var $p = $('#hsy-progress').prop('hidden', !total);
        $p.find('.hsy-progress__bar span').css('width', (total ? Math.round(done * 100 / total) : 0) + '%');
        $p.find('.hsy-progress__text').text(done + ' / ' + total);
    }

    function hsy_apply_result(result) {
        var $tr = $('#hsy-rows tr[data-id="' + result.source_id.replace(/"/g, '\\"') + '"]');
        if (!result.ok) {
            $tr.find('.hsy-col-status').html('<span class="hsy-error">' + hsy_text(result.message) + '</span>');
            return;
        }
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].id === result.source_id) {
                rows[i].product_id = result.product_id;
                rows[i].edit = result.edit;
                $tr.find('.hsy-col-status').html(hsy_status(rows[i]) +
                    (result.action === 'skipped' ? ' <span class="hsy-muted">(sin cambios)</span>' : '') +
                    (result.message ? '<br><span class="hsy-warning">' + hsy_text(result.message) + '</span>' : ''));
                $tr.find('.hsy-import-one').text('Actualizar');
                break;
            }
        }
    }

    function hsy_import(ids) {
        if (busy || !ids.length) {
            return;
        }
        var total = ids.length;
        var done = 0;
        var errors = 0;
        hsy_busy(true);
        hsy_progress(0, total);
        hsy_message('#hsy-message', 'Importando ' + total + ' productos…', 'info');

        function next() {
            if (!ids.length) {
                hsy_busy(false);
                hsy_message('#hsy-message', 'Importación terminada: ' + (total - errors) + ' correctos' +
                    (errors ? ', ' + errors + ' con error.' : '.'), errors ? 'error' : 'ok');
                return;
            }
            var batch = ids.splice(0, D.batch || 5);
            hsy_post('import', { ids: batch }).then(function (results) {
                for (var i = 0; i < results.length; i++) {
                    if (!results[i].ok) {
                        errors++;
                    }
                    hsy_apply_result(results[i]);
                }
            }, function (error) {
                errors += batch.length;
                for (var i = 0; i < batch.length; i++) {
                    hsy_apply_result({ source_id: batch[i], ok: false, message: error });
                }
            }).always(function () {
                done += batch.length;
                hsy_progress(done, total);
                next();
            });
        }
        next();
    }

    $page.on('click', '.hsy-import-one', function () {
        hsy_import([String($(this).closest('tr').data('id'))]);
    });
    $page.on('click', '#hsy-import-selected', function () {
        hsy_import(hsy_selected_ids());
    });
    $page.on('click', '#hsy-import-all', function () {
        var ids = $.map(hsy_visible(), function (row) {
            return row.id;
        });
        if (ids.length && window.confirm('¿Importar ' + ids.length + ' productos?')) {
            hsy_import(ids);
        }
    });

    // Al abrir la página se muestra el último catálogo leído (sin pedirlo al origen).
    if ($('#hsy-rows').length) {
        hsy_post('rows', {}).then(hsy_set_rows);
    }
});
