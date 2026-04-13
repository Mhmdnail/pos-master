<?php
namespace App\Controllers\Api;
use App\Models\RawMaterialModel;
use App\Models\StockMovementModel;

class MaterialController extends BaseApiController
{
    protected RawMaterialModel $model;

    public function __construct()
    {
        helper(['uuid','code']);
        $this->model = new RawMaterialModel();
    }

    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page') ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 100);

        $total = $this->model->forOutlet($outletId)->where('active',1)->countAllResults(false);
        $data  = $this->model->forOutlet($outletId)->where('active',1)->orderBy('name','ASC')->paginate($perPage,'default',$page);
        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    public function show($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Bahan baku tidak ditemukan');
        return $this->success($row);
    }

    public function lowStock()
    {
        $data = $this->model->getLowStock($this->currentOutletId());
        return $this->success($data);
    }

    public function create()
    {
        $json  = $this->request->getJSON(true) ?? [];
        $rules = ['name' => 'required', 'unit' => 'required'];
        if (!$this->validate($rules)) return $this->error('Validasi gagal', 422, $this->validator->getErrors());

        $outletId = $this->currentOutletId();
        $data = [
            'id'            => generate_uuid(),
            'code'          => generate_code('raw_materials', $outletId),
            'outlet_id'     => $outletId,
            'name'          => $json['name'],
            'unit'          => $json['unit'],
            'stock_qty'     => $json['stock_qty'] ?? 0,
            'min_stock'     => $json['min_stock'] ?? 0,
            'cost_per_unit' => $json['cost_per_unit'] ?? 0,
            'expired_at'    => $json['expired_at'] ?? null,
            'active'        => 1,
        ];
        $this->model->insert($data);
        return $this->created($data, 'Bahan baku berhasil dibuat');
    }

    public function update($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Bahan baku tidak ditemukan');

        $json    = $this->request->getJSON(true) ?? [];
        $allowed = ['name','unit','min_stock','cost_per_unit','expired_at','active'];
        $data    = array_intersect_key($json, array_flip($allowed));
        $this->model->update($id, $data);
        return $this->success(array_merge($row,$data), 'Bahan baku berhasil diupdate');
    }

    public function delete($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Bahan baku tidak ditemukan');
        $this->model->update($id, ['active' => 0]);
        return $this->success(null, 'Bahan baku berhasil dihapus');
    }

    public function adjust($id)
    {
        $row  = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Bahan baku tidak ditemukan');

        $json  = $this->request->getJSON(true) ?? [];
        $type  = $json['type'] ?? 'in';    // in | out
        $qty   = (float)($json['qty'] ?? 0);
        $notes = $json['notes'] ?? 'Penyesuaian manual';

        if ($qty <= 0) return $this->error('Jumlah harus lebih dari 0');

        $before = (float)$row['stock_qty'];
        $after  = $type === 'in' ? $before + $qty : $before - $qty;
        if ($after < 0) return $this->error('Stok tidak mencukupi untuk pengurangan');

        $db = \Config\Database::connect();
        $db->transStart();
        $db->query('UPDATE raw_materials SET stock_qty=?, updated_at=NOW() WHERE id=?', [$after, $id]);
        (new StockMovementModel())->insert([
            'id'             => generate_uuid(),
            'outlet_id'      => $this->currentOutletId(),
            'material_id'    => $id,
            'reference_type' => 'adjustment',
            'movement_type'  => $type,
            'qty'            => $qty,
            'qty_before'     => $before,
            'qty_after'      => $after,
            'cost_per_unit'  => $row['cost_per_unit'],
            'notes'          => $notes,
            'created_by'     => $this->currentUserId(),
        ]);
        $db->transComplete();

        return $this->success(['qty_before'=>$before,'qty_after'=>$after], 'Stok berhasil disesuaikan');
    }
}


class RecipeController extends BaseApiController
{
    public function __construct() { helper(['uuid']); }

    public function show($productId)
    {
        $recipe = (new \App\Models\BomRecipeModel())->getActiveRecipe($productId);
        if (!$recipe) return $this->success(null);
        $recipe['lines'] = (new \App\Models\BomRecipeLineModel())->getByRecipe($recipe['id']);
        return $this->success($recipe);
    }

    public function save($productId)
    {
        $json  = $this->request->getJSON(true) ?? [];
        $lines = $json['lines'] ?? [];
        if (empty($lines)) return $this->error('Minimal 1 bahan baku diperlukan');

        $recipeModel = new \App\Models\BomRecipeModel();
        $lineModel   = new \App\Models\BomRecipeLineModel();

        $recipeModel->where('product_id',$productId)->set(['active'=>0])->update();
        $recipeId = generate_uuid();
        $recipeModel->insert(['id'=>$recipeId,'product_id'=>$productId,'name'=>$json['name']??'Default','active'=>1]);

        foreach ($lines as $line) {
            $lineModel->insert(['id'=>generate_uuid(),'recipe_id'=>$recipeId,'material_id'=>$line['material_id'],'qty_required'=>$line['qty_required'],'notes'=>$line['notes']??null]);
        }
        (new \App\Models\ProductModel())->update($productId, ['has_bom'=>1]);

        return $this->success(['recipe_id'=>$recipeId], 'Resep berhasil disimpan');
    }
}
