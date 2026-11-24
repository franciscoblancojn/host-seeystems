<?php

/**
 * Último catálogo leído del origen (opción HSY_CATALOG, sin autoload).
 *
 * Es la única fuente de los productos que se pueden importar: la tabla y el importador
 * leen de aquí, para no volver a pedir el catálogo al origen en cada lote.
 * Formato: ['fetched_at' => timestamp, 'items' => [source_id => producto normalizado]].
 */
class HSY_USE_DATA_CATALOG extends HSY_USE_DATA_BASE
{
    protected $KEY = HSY_CATALOG;

    public function items()
    {
        return (array) ($this->DATA['items'] ?? []);
    }

    public function item($id)
    {
        $items = $this->items();
        return $items[(string) $id] ?? null;
    }

    public function fetched_at()
    {
        return (int) ($this->DATA['fetched_at'] ?? 0);
    }

    public function set_items($items)
    {
        $this->DATA = [
            'fetched_at' => time(),
            'items' => $items,
        ];
        $this->save();
    }
}
