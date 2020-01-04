<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Kernel;
use Symfony\Component\HttpFoundation\JsonResponse;

class ItemController
{
    public function index(): JsonResponse
    {
        $db = Kernel::getDatabase();

        $rows = $db->select('select items.* from items inner join item_data as data on data.id = items.use_version order by product_code asc limit 15 offset 0');
        $dataIds = \array_map(fn(\stdClass $row) => $row->use_version, $rows);

        $placeholders = \substr(\str_repeat(',?', \count($rows)), 1);
        $data = $db->select("SELECT * FROM item_data WHERE id IN ({$placeholders})", $dataIds);

        foreach ($data as $itemData) {
            foreach ($rows as $row) {
                if ($itemData->item_id === $row->id) {
                    $itemData->barcodes = [];

                    $row->data = $itemData;
                    break;
                }
            }
        }

        $placeholders = \substr(\str_repeat(',?', \count($rows)), 1);
        $barcodes = $db->select("SELECT * FROM barcodes WHERE item_data_id IN ({$placeholders})", $dataIds);

        foreach ($barcodes as $barcode) {
            foreach ($rows as $row) {
                if ($barcode->item_data_id === $row->data->id) {
                    $row->data->barcodes[] = $barcode;
                }
            }
        }

        return new JsonResponse(['data' => $rows]);
    }
}
