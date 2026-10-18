/* global jQuery, HSEY_IMPORT */
// Carga de productos a WooCommerce por lotes (wp_ajax_hsey_import_batch).
jQuery(function ($) {
    var $box = $('#hsey-import');
    if (!$box.length) {
        return;
    }

    var $start = $box.find('.hsey-import-start');
    var $stop = $box.find('.hsey-import-stop');
    var $progress = $box.find('.hsey-import-progress');
    var $status = $box.find('.hsey-import-status');
    var $errors = $box.find('.hsey-import-errors');
    var total = parseInt($box.data('total'), 10) || 0;
    var stopped = false;
    var totals = { created: 0, updated: 0, errors: 0 };

    function hsey_import_status(offset) {
        $progress.val(offset);
        $status.text(
            offset + ' / ' + total + ' — creados: ' + totals.created +
            ', actualizados: ' + totals.updated + ', errores: ' + totals.errors
        );
    }

    function hsey_import_finish(message) {
        $start.prop('disabled', false).removeClass('fwue-loader');
        $stop.prop('hidden', true);
        $status.text($status.text() + ' — ' + message);
    }

    function hsey_import_batch(offset) {
        if (stopped) {
            hsey_import_finish('Detenido.');
            return;
        }
        $.ajax({
            url: HSEY_IMPORT.ajaxUrl,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'hsey_import_batch',
                nonce: HSEY_IMPORT.nonce,
                offset: offset,
                limit: HSEY_IMPORT.batch
            }
        }).done(function (res) {
            if (!res || !res.success) {
                hsey_import_finish('Error: ' + (res && res.data ? res.data : 'respuesta inválida'));
                return;
            }
            var data = res.data;
            totals.created += data.created;
            totals.updated += data.updated;
            totals.errors += data.errors.length;
            $.each(data.errors, function (i, error) {
                $('<li>').text(error).appendTo($errors);
            });
            hsey_import_status(data.next);
            if (data.next >= data.total) {
                hsey_import_finish('Carga completa.');
                return;
            }
            hsey_import_batch(data.next);
        }).fail(function (xhr) {
            hsey_import_finish('Error HTTP ' + xhr.status + '. Puedes volver a cargar: los productos ya creados se actualizan.');
        });
    }

    $start.on('click', function () {
        if (!window.confirm('¿Cargar ' + total + ' productos en WooCommerce?')) {
            $start.removeClass('fwue-loader');
            return;
        }
        stopped = false;
        totals = { created: 0, updated: 0, errors: 0 };
        $errors.empty();
        $start.prop('disabled', true);
        $stop.prop('hidden', false);
        $progress.prop('hidden', false);
        hsey_import_status(0);
        hsey_import_batch(0);
    });

    $stop.on('click', function () {
        stopped = true;
        $stop.prop('hidden', true);
    });
});
