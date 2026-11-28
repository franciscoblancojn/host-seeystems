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
        $page.find('#hsy-load, #hsy-import-selected, #hsy-import-all, .hsy-import-one, .hsy-regenerate-one, #hsy-regenerate-selected, #hsy-content-pending, #hsy-publish').prop('disabled', value);
        $('#hsy-spinner').toggleClass('is-active', value);
        if (!value) {
            hsy_update_count();
            $('#hsy-publish').prop('disabled', !stats.publishable);
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

    // Stock del catálogo y lo que quedará en la tienda con la regla del stock mínimo.
    function hsy_stock(stock) {
        if (stock === null || typeof stock === 'undefined') {
            return '—';
        }
        var min = parseInt(D.min_stock, 10) || 0;
        if (min > 0 && stock > 0 && stock <= min) {
            return hsy_text(stock) + ' <span class="hsy-warning" title="Stock mínimo: ' + min + '">→ 0</span>';
        }
        return hsy_text(stock);
    }

    // Descripción en la tabla: 20 caracteres y el texto completo en un tooltip; badge si está pendiente.
    var HSY_TEXT_PREVIEW = 20;

    function hsy_text_cell(row, text, pending) {
        if (!row.product_id) {
            return '<span class="hsy-muted">—</span>';
        }
        if (pending) {
            return '<span class="hsy-badge hsy-badge--pending">Pendiente de generar con IA</span>';
        }
        text = String(text || '');
        var short = text.length > HSY_TEXT_PREVIEW ? $.trim(text.slice(0, HSY_TEXT_PREVIEW)) + '…' : text;
        return '<span class="hsy-tip" tabindex="0" data-tip="' + hsy_text(text) + '">' + hsy_text(short) + '</span>';
    }

    // Categorías: recortadas con tooltip; badge si la IA aún no eligió el estilo o si no tiene.
    function hsy_categories_cell(row) {
        if (!row.product_id) {
            return '<span class="hsy-muted">—</span>';
        }
        var cats = row.categories || [];
        var html = '';
        if (cats.length && row.has_categories) {
            var text = cats.join(', ');
            var short = text.length > 30 ? $.trim(text.slice(0, 30)) + '…' : text;
            html = '<span class="hsy-tip" tabindex="0" data-tip="' + hsy_text(cats.join('\n')) + '">' + hsy_text(short) + '</span>';
        } else {
            html = '<span class="hsy-badge hsy-badge--new">Sin categoría</span>';
        }
        if (row.style_pending) {
            html += (html ? '<br>' : '') + '<span class="hsy-badge hsy-badge--pending">Pendiente de asignar por IA</span>';
        }
        return html;
    }

    function hsy_row_html(row) {
        return '<tr data-id="' + hsy_text(row.id) + '">' +
            '<th scope="row" class="check-column"><input type="checkbox" class="hsy-check" value="' + hsy_text(row.id) + '"></th>' +
            '<td class="hsy-col-product"><div class="hsy-product">' +
            (row.image ? '<img src="' + hsy_text(row.image) + '" alt="" loading="lazy">' : '<span class="hsy-noimg"></span>') +
            '<div><strong>' + hsy_text(row.name) + '</strong><br><span class="hsy-muted">' + hsy_text(row.brand) + '</span></div>' +
            '</div></td>' +
            '<td class="hsy-nowrap">' + hsy_text(row.sku) + '</td>' +
            '<td class="hsy-nowrap">' + hsy_price(row.pvp) + '</td>' +
            '<td class="hsy-nowrap">' + hsy_price(row.wholesale) + '</td>' +
            '<td>' + hsy_stock(row.stock) +
            (row.availability ? '<br><span class="hsy-muted">' + hsy_text(row.availability) + '</span>' : '') + '</td>' +
            '<td class="hsy-col-text">' + hsy_text_cell(row, row.description, row.description_pending) + '</td>' +
            '<td class="hsy-col-text">' + hsy_text_cell(row, row.short_description, row.short_pending) + '</td>' +
            '<td class="hsy-col-text">' + hsy_categories_cell(row) + '</td>' +
            '<td class="hsy-col-status hsy-nowrap">' + hsy_status(row) + '</td>' +
            '<td class="hsy-col-action"><button type="button" class="button button-small hsy-import-one">' +
            (row.product_id ? 'Actualizar' : 'Importar') + '</button>' +
            (row.product_id ? '<button type="button" class="button button-small hsy-regenerate-one" title="Vuelve a generar con IA la descripción y la descripción corta (reemplaza las actuales)">Regenerar</button>' : '') +
            '</td>' +
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
            // Con contenido faltante: importado al que le falta descripción, corta o categorías.
            if (filter === 'incomplete' && (!row.product_id ||
                !(row.description_pending || row.short_pending || !row.has_categories))) {
                return false;
            }
            return !q || (row.name + ' ' + row.sku + ' ' + row.brand + ' ' + row.availability).toLowerCase().indexOf(q) !== -1;
        });
    }

    // ------------------------------------------------------------------ paginación (de a HSY_PAGE_SIZE)

    var HSY_PAGE_SIZE = 100;
    var currentPage = 1;
    var selected = {}; // ids del catálogo seleccionados (se conserva al cambiar de página)

    // Filas de la página actual (después de buscar y filtrar).
    function hsy_page_rows() {
        var visible = hsy_visible();
        var pages = Math.max(1, Math.ceil(visible.length / HSY_PAGE_SIZE));
        currentPage = Math.min(Math.max(1, currentPage), pages);
        var start = (currentPage - 1) * HSY_PAGE_SIZE;
        return { all: visible, list: visible.slice(start, start + HSY_PAGE_SIZE), start: start, pages: pages };
    }

    function hsy_pager_html(info) {
        if (!info.all.length) {
            return '';
        }
        var from = info.start + 1;
        var to = info.start + info.list.length;
        var btn = function (page, label, title, disabled) {
            return '<button type="button" class="button hsy-page-btn" data-page="' + page + '" title="' + title + '"' +
                (disabled ? ' disabled' : '') + '>' + label + '</button>';
        };
        return '<span class="hsy-muted">Mostrando ' + from + '–' + to + ' de ' + info.all.length + '</span>' +
            '<span class="hsy-pager__nav">' +
            btn(1, '«', 'Primera página', currentPage === 1) +
            btn(currentPage - 1, '‹', 'Página anterior', currentPage === 1) +
            '<span class="hsy-pager__current">Página <input type="number" class="small-text hsy-page-input" min="1" max="' + info.pages + '" value="' + currentPage + '"> de ' + info.pages + '</span>' +
            btn(currentPage + 1, '›', 'Página siguiente', currentPage === info.pages) +
            btn(info.pages, '»', 'Última página', currentPage === info.pages) +
            '</span>';
    }

    function hsy_render() {
        var info = hsy_page_rows();
        var html = [];
        for (var i = 0; i < info.list.length; i++) {
            html.push(hsy_row_html(info.list[i]));
        }
        $('#hsy-rows').html(html.length ? html.join('') : '<tr><td colspan="11">No hay productos.</td></tr>');
        // Marcar las filas seleccionadas (la selección se conserva entre páginas).
        $('#hsy-rows .hsy-check').each(function () {
            this.checked = !!selected[this.value];
        });
        $('#hsy-total').text(info.all.length);
        $('#hsy-page-count').text(info.list.length);
        $page.find('.hsy-pager').html(hsy_pager_html(info)).prop('hidden', !info.all.length);
        $page.find('.hsy-actions, .hsy-table').prop('hidden', !rows.length);
        var $checks = $('#hsy-rows .hsy-check');
        $page.find('.hsy-check-all').prop('checked', $checks.length > 0 && $checks.length === $checks.filter(':checked').length);
        hsy_update_count();
    }

    function hsy_go_to(page) {
        currentPage = parseInt(page, 10) || 1;
        hsy_render();
        var top = $page.find('.hsy-actions').offset();
        if (top) {
            $('html, body').scrollTop(top.top - 40);
        }
    }

    $page.on('click', '.hsy-page-btn', function () {
        hsy_go_to($(this).data('page'));
    });
    $page.on('change', '.hsy-page-input', function () {
        hsy_go_to($(this).val());
    });

    // Ids seleccionados que siguen en el catálogo (de cualquier página).
    function hsy_selected_ids() {
        var ids = [];
        for (var i = 0; i < rows.length; i++) {
            if (selected[rows[i].id]) {
                ids.push(rows[i].id);
            }
        }
        return ids;
    }

    function hsy_update_count() {
        var n = hsy_selected_ids().length;
        $('#hsy-selected-count').text(n);
        $('#hsy-clear-selection').prop('hidden', !n);
        $('#hsy-import-selected').prop('disabled', busy || !n);
        var m = hsy_selected_product_ids().length;
        $('#hsy-regenerate-count').text(m);
        $('#hsy-regenerate-selected').prop('disabled', busy || !m || !D.content_enabled);
    }

    // Productos de la tienda (ya importados) entre las filas marcadas.
    function hsy_selected_product_ids() {
        var ids = hsy_selected_ids();
        var out = [];
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].product_id && $.inArray(rows[i].id, ids) !== -1) {
                out.push(rows[i].product_id);
            }
        }
        return out;
    }

    function hsy_set_rows(data) {
        rows = data.rows || [];
        $('#hsy-fetched').text(data.fetched_at ? 'Última lectura: ' + data.fetched_at : '');
        hsy_set_pending(data.pending);
        hsy_set_stats(data.stats);
        hsy_render();
    }

    // «Seleccionar todo» marca o desmarca solo la página actual.
    $page.on('change', '.hsy-check-all', function () {
        var on = this.checked;
        $('#hsy-rows .hsy-check').each(function () {
            this.checked = on;
            if (on) {
                selected[this.value] = true;
            } else {
                delete selected[this.value];
            }
        });
        hsy_update_count();
    });
    $page.on('change', '.hsy-check', function () {
        if (this.checked) {
            selected[this.value] = true;
        } else {
            delete selected[this.value];
        }
        var $checks = $('#hsy-rows .hsy-check');
        $page.find('.hsy-check-all').prop('checked', $checks.length === $checks.filter(':checked').length);
        hsy_update_count();
    });
    $page.on('click', '#hsy-clear-selection', function () {
        selected = {};
        hsy_render();
    });
    // Buscar o filtrar vuelve a la página 1.
    $page.on('input', '#hsy-search', function () {
        currentPage = 1;
        hsy_render();
    });
    $page.on('change', '#hsy-filter', function () {
        currentPage = 1;
        hsy_render();
    });

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

    // Barra de progreso: #hsy-progress (importación / general) o #hsy-progress-ai (cola de IA).
    function hsy_progress(done, total, selector, label) {
        var $p = $(selector || '#hsy-progress').prop('hidden', !total);
        $p.find('.hsy-progress__bar span').css('width', (total ? Math.round(done * 100 / total) : 0) + '%');
        $p.find('.hsy-progress__text').text((label ? label + ' ' : '') + done + ' / ' + total);
    }

    // Repinta la fila de un producto con los textos que devuelve la IA (sin recargar la tabla).
    function hsy_apply_content(result) {
        if (!result || !result.row) {
            return;
        }
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].product_id === result.product_id) {
                $.extend(rows[i], result.row);
                var $tr = $('#hsy-rows tr[data-id="' + String(rows[i].id).replace(/"/g, '\\"') + '"]');
                if ($tr.length) {
                    var checked = $tr.find('.hsy-check').prop('checked');
                    var $new = $(hsy_row_html(rows[i]));
                    $new.find('.hsy-check').prop('checked', checked);
                    $new.addClass('hsy-row--updated');
                    $tr.replaceWith($new);
                }
                return;
            }
        }
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
                if (result.row) {
                    $.extend(rows[i], result.row);
                }
                // Fila completa (descripciones, categorías, botones) + avisos de la importación.
                if ($tr.length) {
                    var checked = $tr.find('.hsy-check').prop('checked');
                    var $new = $(hsy_row_html(rows[i]));
                    $new.find('.hsy-check').prop('checked', checked);
                    $new.find('.hsy-col-status').append(
                        (result.action === 'skipped' ? ' <span class="hsy-muted">(sin cambios)</span>' : '') +
                        (result.message ? '<br><span class="hsy-warning">' + hsy_text(result.message) + '</span>' : ''));
                    $tr.replaceWith($new);
                }
                break;
            }
        }
    }

    // ------------------------------------------------------------------ descripciones pendientes

    function hsy_set_pending(count) {
        count = parseInt(count, 10) || 0;
        $('#hsy-pending-count').text(count);
        $page.find('.hsy-content-bar').prop('hidden', !count);
        $('#hsy-content-pending').prop('disabled', busy || !count)
            .attr('title', D.content_enabled ? '' : 'Sin IA solo se asignan las categorías; para descripciones y estilo activa la IA en Importación.');
    }

    // ------------------------------------------------------------------ contadores y publicar

    var stats = {};

    function hsy_set_stats(data) {
        if (!data) {
            return;
        }
        stats = data;
        $page.find('[data-stat]').each(function () {
            var key = $(this).data('stat');
            if (typeof data[key] !== 'undefined') {
                $(this).text(Number(data[key]).toLocaleString());
            }
        });
        $('#hsy-publish').prop('disabled', busy || !data.publishable);
    }

    $page.on('click', '#hsy-publish', function () {
        if (busy) {
            return;
        }
        hsy_post('publishable', {}).then(function (ids) {
            if (!ids.length) {
                hsy_message('#hsy-message', 'No hay borradores importados con contenido completo.', 'info');
                return;
            }
            if (!window.confirm('¿Publicar ' + ids.length + ' productos? Pasan de borrador a publicados y se verán en la tienda.')) {
                return;
            }
            var total = ids.length;
            var count = 0;
            var errors = 0;
            var last = '';
            hsy_busy(true);
            hsy_progress(0, total);
        $('#hsy-progress-ai').prop('hidden', true);
            $('#hsy-progress-ai').prop('hidden', true);
            hsy_message('#hsy-message', 'Publicando ' + total + ' productos…', 'info');

            function next() {
                if (!ids.length) {
                    hsy_busy(false);
                    hsy_message('#hsy-message', 'Publicados: ' + (total - errors) +
                        (errors ? ', ' + errors + ' sin publicar (' + last + ').' : '.'), errors ? 'error' : 'ok');
                    hsy_refresh_pending();
                    return;
                }
                var batch = ids.splice(0, 20);
                hsy_post('publish', { ids: batch }).then(function (results) {
                    for (var i = 0; i < results.length; i++) {
                        if (!results[i].ok) {
                            errors++;
                            last = results[i].message;
                        }
                    }
                }, function (error) {
                    errors += batch.length;
                    last = error;
                }).always(function () {
                    count += batch.length;
                    hsy_progress(count, total);
                    next();
                });
            }
            next();
        });
    });

    // Vuelve a pedir las filas (estado, descripciones y pendientes) sin leer el catálogo de origen.
    function hsy_refresh_pending() {
        hsy_post('rows', {}).then(hsy_set_rows);
    }

    // Genera descripción y descripción corta de los productos indicados, de a D.content_batch.
    // Resultado de un producto en las colas de IA: ok / parcial (p. ej. categorías asignadas) / error.
    // Devuelve true si hay que detener la cola (ninguna IA disponible).
    function hsy_tally(result, stats) {
        hsy_apply_content(result);
        if (result.ok) {
            stats.ok++;
        } else if (result.partial && result.partial.length) {
            stats.partial++;
            stats.last = result.message;
        } else {
            stats.errors++;
            stats.last = result.message;
        }
        return !!result.stop;
    }

    function hsy_tally_text(stats) {
        return stats.ok + ' completos' +
            (stats.partial ? ', ' + stats.partial + ' parciales (categorías asignadas, sin texto de IA)' : '') +
            (stats.errors ? ', ' + stats.errors + ' con error' : '');
    }

    /**
     * Cola de IA de a D.content_batch: «Generar faltantes» o «Regenerar» ($force).
     * Si ninguna IA responde (todas en pausa / sin cuota / mal configuradas) se detiene ahí mismo.
     */
    function hsy_content(productIds, done, force) {
        var total = productIds.length;
        var count = 0;
        var stats = { ok: 0, partial: 0, errors: 0, last: '' };
        var stopped = false;
        if (!total) {
            done();
            return;
        }
        hsy_busy(true);
        hsy_progress(0, total);
        $('#hsy-progress-ai').prop('hidden', true);
        hsy_message('#hsy-message', (force ? 'Regenerando descripciones de ' : 'Completando lo que les falta a ') + total + ' productos…', 'info');

        function next() {
            if (stopped || !productIds.length) {
                hsy_busy(false);
                var text = (force ? 'Descripciones regeneradas: ' : 'Productos procesados: ') + hsy_tally_text(stats) + '.';
                if (stopped) {
                    text = 'Detenido: ninguna IA disponible (' + stats.last + '). ' + text + ' Quedan ' + productIds.length +
                        ' sin procesar: vuelve a intentarlo cuando las IA salgan de la pausa o añade otra conexión.';
                } else if (stats.last) {
                    text += ' Último error: ' + stats.last;
                }
                hsy_message('#hsy-message', text, stopped || stats.errors ? 'error' : 'ok');
                hsy_refresh_pending();
                done();
                return;
            }
            var batch = productIds.splice(0, D.content_batch || 1);
            hsy_post('content', { ids: batch, force: force ? 1 : 0 }).then(function (results) {
                for (var i = 0; i < results.length; i++) {
                    if (hsy_tally(results[i], stats)) {
                        stopped = true;
                    }
                }
            }, function (error) {
                stats.errors += batch.length;
                stats.last = error;
            }).always(function () {
                count += batch.length;
                hsy_progress(count, total);
                next();
            });
        }
        next();
    }

    $page.on('click', '#hsy-content-pending', function () {
        if (busy) {
            return;
        }
        hsy_post('pending', {}).then(function (ids) {
            if (ids.length && window.confirm('¿Completar lo que les falta a ' + ids.length + ' productos importados (categorías, estilo, descripción y/o descripción corta)? Solo se genera lo que falta; lo que ya tienen no se toca.')) {
                hsy_content(ids, function () {});
            }
        });
    });

    // Casilla «Generar contenido al importar»: activada por defecto; la elección se recuerda en este navegador.
    var HSY_AUTO_KEY = 'hsy_auto_content';
    function hsy_auto_content() {
        return !!D.content_enabled && $('#hsy-auto-content').is(':checked');
    }
    (function () {
        var $box = $('#hsy-auto-content');
        if (!$box.length) {
            return;
        }
        if (!D.content_enabled) {
            $box.prop({ checked: false, disabled: true }).closest('label')
                .attr('title', 'Activa la IA en Importación → Descripciones para generar contenido al importar.');
            return;
        }
        try {
            if ('0' === window.localStorage.getItem(HSY_AUTO_KEY)) {
                $box.prop('checked', false);
            }
        } catch (e) {}
        $box.on('change', function () {
            try {
                window.localStorage.setItem(HSY_AUTO_KEY, this.checked ? '1' : '0');
            } catch (e) {}
        });
    }());

    /**
     * Importación con dos colas en paralelo:
     * - importación de a D.batch (5) productos;
     * - IA de a D.content_batch (1): cada lote importado entra en la cola de la IA en cuanto termina,
     *   sin esperar al resto. Así nunca hay más de 2 peticiones a la vez.
     */
    function hsy_import(ids) {
        if (busy || !ids.length) {
            return;
        }
        var withAi = hsy_auto_content();
        var imp = { total: ids.length, done: 0, errors: 0, running: true };
        var ai = { queue: [], total: 0, done: 0, running: false, stopped: false, stats: { ok: 0, partial: 0, errors: 0, last: '' } };
        hsy_busy(true);
        hsy_progress(0, imp.total, '#hsy-progress', 'Importación');
        $('#hsy-progress-ai').prop('hidden', true);
        hsy_status_message();

        function hsy_status_message(final) {
            var text = (final ? 'Terminado. ' : '') + 'Importación: ' + imp.done + '/' + imp.total +
                (imp.errors ? ' (' + imp.errors + ' con error)' : '');
            if (withAi) {
                text += ' · IA: ' + ai.done + '/' + ai.total + ' (' + hsy_tally_text(ai.stats) + ')';
                if (ai.stopped) {
                    text += ' · IA detenida: ninguna IA disponible (' + ai.stats.last + '). Los productos que quedan siguen «Pendiente de generar con IA»';
                } else if (ai.stats.last) {
                    text += ' · Último error: ' + ai.stats.last;
                }
            }
            hsy_message('#hsy-message', text + (final ? '' : '…'), final ? (imp.errors || ai.stats.errors || ai.stopped ? 'error' : 'ok') : 'info');
        }

        function finish() {
            if (imp.running || ai.running) {
                return;
            }
            hsy_busy(false);
            hsy_status_message(true);
            hsy_refresh_pending();
        }

        function aiNext() {
            if (!ai.queue.length) {
                ai.running = false;
                finish();
                return;
            }
            ai.running = true;
            var batch = ai.queue.splice(0, D.content_batch || 1);
            hsy_post('content', { ids: batch }).then(function (results) {
                for (var i = 0; i < results.length; i++) {
                    if (hsy_tally(results[i], ai.stats)) {
                        // Ninguna IA disponible: se vacía la cola de IA; la importación sigue.
                        ai.stopped = true;
                        ai.queue = [];
                    }
                }
            }, function (error) {
                ai.stats.errors += batch.length;
                ai.stats.last = error;
            }).always(function () {
                ai.done += batch.length;
                hsy_progress(ai.done, ai.total, '#hsy-progress-ai', 'IA');
                hsy_status_message();
                aiNext();
            });
        }

        function aiAdd(productIds) {
            if (!withAi || ai.stopped || !productIds.length) {
                return;
            }
            ai.queue = ai.queue.concat(productIds);
            ai.total += productIds.length;
            hsy_progress(ai.done, ai.total, '#hsy-progress-ai', 'IA');
            if (!ai.running) {
                aiNext();
            }
        }

        function importNext() {
            if (!ids.length) {
                imp.running = false;
                finish();
                return;
            }
            var batch = ids.splice(0, D.batch || 5);
            var created = [];
            hsy_post('import', { ids: batch }).then(function (results) {
                for (var i = 0; i < results.length; i++) {
                    if (!results[i].ok) {
                        imp.errors++;
                    } else if (results[i].product_id) {
                        created.push(results[i].product_id);
                    }
                    hsy_apply_result(results[i]);
                }
            }, function (error) {
                imp.errors += batch.length;
                for (var i = 0; i < batch.length; i++) {
                    hsy_apply_result({ source_id: batch[i], ok: false, message: error });
                }
            }).always(function () {
                imp.done += batch.length;
                hsy_progress(imp.done, imp.total, '#hsy-progress', 'Importación');
                hsy_status_message();
                aiAdd(created);   // estos 5 pasan a la cola de la IA ya
                importNext();     // y mientras tanto se importan los 5 siguientes
            });
        }
        importNext();
    }

    $page.on('click', '.hsy-import-one', function () {
        hsy_import([String($(this).closest('tr').data('id'))]);
    });
    // Regenerar: reescribe la descripción y la corta con la IA aunque ya existan.
    function hsy_regenerate(productIds) {
        if (busy || !productIds.length) {
            return;
        }
        if (!D.content_enabled) {
            window.alert('Activa la IA en Importación → Descripciones.');
            return;
        }
        var msg = productIds.length === 1
            ? '¿Regenerar la descripción y la descripción corta de este producto? Se reemplazan las actuales, también si se editaron a mano.'
            : '¿Regenerar la descripción y la descripción corta de ' + productIds.length + ' productos? Se reemplazan las actuales, también si se editaron a mano. Cada producto hace dos llamadas a la IA.';
        if (window.confirm(msg)) {
            hsy_content(productIds.slice(0), function () {}, true);
        }
    }

    $page.on('click', '.hsy-regenerate-one', function () {
        var id = String($(this).closest('tr').data('id'));
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].id === id && rows[i].product_id) {
                hsy_regenerate([rows[i].product_id]);
                return;
            }
        }
    });

    $page.on('click', '#hsy-regenerate-selected', function () {
        hsy_regenerate(hsy_selected_product_ids());
    });

    $page.on('click', '#hsy-import-selected', function () {
        hsy_import(hsy_selected_ids());
    });
    // Importa las filas de la página actual (de a 100, para ir revisando).
    $page.on('click', '#hsy-import-all', function () {
        var ids = $.map(hsy_page_rows().list, function (row) {
            return row.id;
        });
        if (ids.length && window.confirm('¿Importar los ' + ids.length + ' productos de esta página (página ' + currentPage + ')?')) {
            hsy_import(ids);
        }
    });

    // ------------------------------------------------------------------ contenido por CSV

    var csvToken = '';

    $page.on('change', '#hsy-csv-missing', function () {
        var $a = $('#hsy-csv-export');
        $a.attr('href', $a.data('base') + (this.checked ? '&missing=1' : ''));
    });

    $page.on('click', '#hsy-csv-preview', function () {
        var file = $('#hsy-csv-file').get(0).files[0];
        if (!file) {
            hsy_message('#hsy-csv-message', 'Elige el archivo CSV.', 'error');
            return;
        }
        var form = new FormData();
        form.append('action', 'hsy_csv_preview');
        form.append('nonce', D.nonce);
        form.append('mode', $('#hsy-csv-mode').val());
        form.append('file', file);
        var $btn = $(this).prop('disabled', true);
        $('#hsy-csv-spinner').addClass('is-active');
        $('#hsy-csv-apply').prop('hidden', true);
        $('#hsy-csv-summary').prop('hidden', true).empty();
        hsy_message('#hsy-csv-message', 'Leyendo el CSV…', 'info');
        $.ajax({ url: D.ajax, method: 'POST', data: form, processData: false, contentType: false, dataType: 'json' }).then(function (res) {
            if (!res || !res.success) {
                hsy_message('#hsy-csv-message', res && res.data ? res.data : 'Error inesperado.', 'error');
                return;
            }
            var d = res.data;
            var sm = d.summary;
            csvToken = d.token;
            hsy_message('#hsy-csv-message', 'Vista previa (' + (d.mode === 'replace' ? 'reemplazar' : 'solo faltantes') + '): ' + sm.rows + ' filas → ' +
                sm.update + ' se actualizarán (' + sm.description + ' descripciones, ' + sm.short + ' descripciones cortas, ' + (sm.style || 0) + ' estilos), ' +
                sm.skip + ' se saltan, ' + sm.error + ' con error. Nada se ha escrito todavía.', sm.update ? 'ok' : 'error');
            if (d.issues && d.issues.length) {
                var html = '<table class="widefat striped"><thead><tr><th>Línea</th><th>Producto</th><th>Qué pasa</th></tr></thead><tbody>';
                for (var i = 0; i < d.issues.length; i++) {
                    html += '<tr><td>' + d.issues[i].line + '</td><td>' + hsy_text(d.issues[i].product_id || d.issues[i].sku) + '</td><td>' +
                        (d.issues[i].action === 'error' ? '<span class="hsy-error">' : '<span class="hsy-muted">') + hsy_text(d.issues[i].message) + '</span></td></tr>';
                }
                $('#hsy-csv-summary').html(html + '</tbody></table>' + (sm.skip + sm.error > d.issues.length ? '<p class="hsy-muted">Se muestran las primeras ' + d.issues.length + '.</p>' : '')).prop('hidden', false);
            }
            $('#hsy-csv-apply').text('Aplicar (' + sm.update + ' productos)').prop('hidden', !sm.update);
        }, function (xhr) {
            hsy_message('#hsy-csv-message', 'Error de conexión (' + xhr.status + ').', 'error');
        }).always(function () {
            $btn.prop('disabled', false);
            $('#hsy-csv-spinner').removeClass('is-active');
        });
    });

    $page.on('click', '#hsy-csv-apply', function () {
        if (busy || !csvToken || !window.confirm('¿Escribir los textos del CSV en los productos?')) {
            return;
        }
        var stats = { ok: 0, partial: 0, errors: 0, last: '' };
        $(this).prop('hidden', true);
        hsy_busy(true);
        $('#hsy-progress-ai').prop('hidden', true);
        function step(offset) {
            hsy_post('csv_apply', { token: csvToken, offset: offset }).then(function (d) {
                for (var i = 0; i < d.results.length; i++) {
                    hsy_tally(d.results[i], stats);
                }
                hsy_progress(d.done, d.total, '#hsy-progress', 'CSV');
                if (d.done < d.total) {
                    step(d.done);
                    return;
                }
                csvToken = '';
                hsy_busy(false);
                hsy_message('#hsy-csv-message', 'Importación del CSV terminada: ' + stats.ok + ' productos actualizados' +
                    (stats.errors ? ', ' + stats.errors + ' con error (' + stats.last + ')' : '') + '.', stats.errors ? 'error' : 'ok');
                hsy_refresh_pending();
            }, function (error) {
                hsy_busy(false);
                hsy_message('#hsy-csv-message', error, 'error');
            });
        }
        step(0);
    });

    // Al abrir la página se muestra el último catálogo leído (sin pedirlo al origen).
    if ($('#hsy-rows').length) {
        hsy_post('rows', {}).then(hsy_set_rows);
    }

    // ------------------------------------------------------------------ prompts (pestaña Importación)

    var $lastPrompt = $('#hsy-prompt-description');
    $page.on('focus', '.hsy-prompt-input', function () {
        $lastPrompt = $(this);
    });

    // Clic en una variable: se inserta donde está el cursor del último prompt usado.
    $page.on('click', '.hsy-var', function () {
        var el = $lastPrompt.get(0);
        if (!el) {
            return;
        }
        var text = $(this).text();
        var start = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
        var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : el.value.length;
        el.value = el.value.slice(0, start) + text + el.value.slice(end);
        el.focus();
        el.selectionStart = el.selectionEnd = start + text.length;
    });

    $page.on('click', '#hsy-reset-prompts', function () {
        if (!window.confirm('¿Restablecer los prompts de descripción, descripción corta y estilo, y el cierre, a los de por defecto? Se pierden los cambios que hayas hecho en ellos.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $('#hsy-reset-spinner').addClass('is-active');
        hsy_post('reset_prompts', {}).then(function (text) {
            hsy_message('#hsy-reset-message', text + ' Recargando…', 'ok');
            // Navegación nueva (no reload): al recargar, el navegador vuelve a rellenar el formulario con el texto anterior.
            window.location.href = window.location.pathname + '?page=' + encodeURIComponent(D.page) + '&tab=importacion&hsy_reset=' + new Date().getTime();
        }, function (error) {
            hsy_message('#hsy-reset-message', error, 'error');
            $btn.prop('disabled', false);
            $('#hsy-reset-spinner').removeClass('is-active');
        });
    });

    $page.on('click', '.hsy-prompt-reset', function () {
        var $target = $($(this).data('target'));
        if (window.confirm('¿Restaurar el prompt por defecto? Se pierde el texto actual al guardar.')) {
            $target.val($target.data('default'));
        }
    });

    // Conexiones de IA: subir / bajar cambia la prioridad (el orden del formulario es el que se guarda).
    $page.on('click', '.hsy-move', function () {
        var $li = $(this).closest('.hsy-connection');
        if ($(this).data('dir') === 'up') {
            $li.prev('.hsy-connection').before($li);
        } else {
            $li.next('.hsy-connection').after($li);
        }
    });

    $page.on('click', '.hsy-ai-resume', function () {
        var $btn = $(this).prop('disabled', true);
        hsy_post('ai_resume', { key: $btn.data('key') }).then(function () {
            $btn.prev('.hsy-badge').remove();
            $btn.remove();
        }, function (error) {
            $btn.prop('disabled', false);
            window.alert(error);
        });
    });

    $page.on('click', '#hsy-preview', function () {
        var productId = $('#hsy-preview-product').val();
        if (!productId) {
            hsy_message('#hsy-preview-message', 'Elige un producto importado.', 'error');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $('#hsy-preview-spinner').addClass('is-active');
        hsy_message('#hsy-preview-message', 'Generando… (dos llamadas a la IA)', 'info');
        $('#hsy-preview-result').prop('hidden', true).empty();
        hsy_post('preview', {
            product_id: productId,
            prompt_description: $('#hsy-prompt-description').val(),
            prompt_short: $('#hsy-prompt-short').val(),
            prompt_style: $('#hsy-prompt-style').val()
        }).then(function (data) {
            hsy_message('#hsy-preview-message', 'Vista previa de «' + data.product + '» con ' + (data.connections || []).join(', ') + '. No se guardó nada.' +
                (data.web ? ' Información de la web: ' + data.web : '') +
                (data.warnings && data.warnings.length ? ' (' + data.warnings.join(' · ') + ')' : ''), 'ok');
            // El HTML ya viene limpio del servidor (wp_kses con las etiquetas permitidas).
            var $r = $('#hsy-preview-result');
            if (data.style !== null) {
                $r.append($('<h4>').text('Estilo (subcategorías)'), $('<div class="hsy-preview__html">').text(
                    (data.style.length ? data.style.join(', ') : 'ninguno') + '  —  opciones: ' + (data.style_options.join(', ') || 'ninguna') +
                    (data.style_answer ? '  —  respuesta de la IA: «' + data.style_answer + '»' : '')));
            }
            $r.append($('<h4>').text('Descripción corta'), $('<div class="hsy-preview__html">').html(data.short_description));
            $r.append($('<h4>').text('Descripción'), $('<div class="hsy-preview__html">').html(data.description));
            $r.append($('<details>').append($('<summary>').text('Prompts enviados'),
                $('<pre class="hsy-prompt">').text(data.prompt_description),
                $('<pre class="hsy-prompt">').text(data.prompt_short),
                $('<pre class="hsy-prompt">').text(data.prompt_style || '')));
            $r.prop('hidden', false);
        }, function (error) {
            hsy_message('#hsy-preview-message', error, 'error');
        }).always(function () {
            $btn.prop('disabled', false);
            $('#hsy-preview-spinner').removeClass('is-active');
        });
    });
});
