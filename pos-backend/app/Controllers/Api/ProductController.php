<?php
namespace App\Controllers\Api;
use App\Models\ProductModel;
use App\Models\ProductModifierModel;
use App\Models\ModifierOptionModel;
use App\Models\BomRecipeModel;
use App\Models\BomRecipeLineModel;
use App\Models\RawMaterialModel;

class ProductController extends BaseApiController
{
    protected ProductModel $model;

    public function __construct()
    {
        helper(['uuid','code']);
        $this->model = new ProductModel();
    }

    public function index()
    {
        $outletId  = $this->currentOutletId();
        $page      = (int)($this->request->getGet('page') ?? 1);
        $perPage   = (int)($this->request->getGet('per_page') ?? 50);
        $category  = $this->request->getGet('category_id');
        $search    = $this->request->getGet('search');

        $builder = $this->model->withCategory()->forOutlet($outletId);
        if ($category) $builder->where('products.category_id', $category);
        if ($search)   $builder->groupStart()->like('products.name',$search)->orLike('products.sku',$search)->groupEnd();

        $total = $builder->countAllResults(false);
        $data  = $builder->orderBy('categories.sort_order','ASC')->orderBy('products.name','ASC')
                         ->paginate($perPage,'default',$page);

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    public function show($id)
    {
        $outletId = $this->currentOutletId();
        $product  = $this->model->withCategory()->forOutlet($outletId)->find($id);
        if (!$product) return $this->notFound('Produk tidak ditemukan');

        // Lampirkan modifier
        $modifierModel = new ProductModifierModel();
        $optionModel   = new ModifierOptionModel();
        $modifiers     = $modifierModel->where('product_id',$id)->orderBy('sort_order')->findAll();
        foreach ($modifiers as &$mod) {
            $mod['options'] = $optionModel->where('modifier_id',$mod['id'])->orderBy('sort_order')->findAll();
        }
        $product['modifiers'] = $modifiers;

        // Lampirkan BOM jika ada
        if ($product['has_bom']) {
            $recipeModel = new BomRecipeModel();
            $lineModel   = new BomRecipeLineModel();
            $recipe      = $recipeModel->getActiveRecipe($id);
            if ($recipe) {
                $recipe['lines']      = $lineModel->getByRecipe($recipe['id']);
                $product['recipe']    = $recipe;
            }
        }

        return $this->success($product);
    }

    public function stockInfo($id)
    {
        $recipeModel = new BomRecipeModel();
        $lineModel   = new BomRecipeLineModel();
        $matModel    = new RawMaterialModel();

        $recipe = $recipeModel->getActiveRecipe($id);
        if (!$recipe) return $this->success(['available' => true, 'lines' => []]);

        $lines = $lineModel->getByRecipe($recipe['id']);
        $canMake = PHP_INT_MAX;

        foreach ($lines as &$line) {
            $mat = $matModel->find($line['material_id']);
            $line['stock_available'] = (float)($mat['stock_qty'] ?? 0);
            $possible = $line['qty_required'] > 0
                ? floor($line['stock_available'] / $line['qty_required'])
                : PHP_INT_MAX;
            $canMake = min($canMake, $possible);
        }

        return $this->success([
            'can_make' => $canMake === PHP_INT_MAX ? 0 : $canMake,
            'lines'    => $lines,
        ]);
    }

    public function create()
    {
        $json  = $this->request->getJSON(true) ?? [];
        $rules = [
            'name'        => 'required|max_length[150]',
            'sku'         => 'required|max_length[50]',
            'base_price'  => 'required|numeric',
            'category_id' => 'required',
        ];
        if (!$this->validate($rules)) return $this->error('Validasi gagal', 422, $this->validator->getErrors());

        $outletId = $this->currentOutletId();
        $id       = generate_uuid();
        $data = [
            'id'          => $id,
            'code'        => generate_code('products', $outletId),
            'outlet_id'   => $outletId,
            'category_id' => $json['category_id'],
            'name'        => $json['name'],
            'sku'         => $json['sku'],
            'description' => $json['description'] ?? null,
            'base_price'  => $json['base_price'],
            'is_bundle'   => $json['is_bundle'] ?? 0,
            'has_bom'     => $json['has_bom'] ?? 0,
            'image_url'   => $json['image_url'] ?? null,
            'active'      => 1,
        ];
        $this->model->insert($data);

        // Simpan modifiers jika ada
        if (!empty($json['modifiers'])) {
            $this->saveModifiers($id, $json['modifiers']);
        }

        // Simpan BOM jika ada
        if (!empty($json['recipe'])) {
            $this->saveRecipe($id, $json['recipe']);
            $this->model->update($id, ['has_bom' => 1]);
        }

        return $this->created($data, 'Produk berhasil dibuat');
    }

    public function update($id)
    {
        $outletId = $this->currentOutletId();
        $product  = $this->model->forOutlet($outletId)->find($id);
        if (!$product) return $this->notFound('Produk tidak ditemukan');

        $json    = $this->request->getJSON(true) ?? [];
        $allowed = ['category_id','name','sku','description','base_price','is_bundle','has_bom','image_url','active'];
        $data    = array_intersect_key($json, array_flip($allowed));
        $this->model->update($id, $data);

        if (!empty($json['modifiers'])) $this->saveModifiers($id, $json['modifiers']);
        if (!empty($json['recipe']))    $this->saveRecipe($id, $json['recipe']);

        return $this->success(array_merge($product, $data), 'Produk berhasil diupdate');
    }

    public function delete($id)
    {
        $product = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$product) return $this->notFound('Produk tidak ditemukan');
        $this->model->update($id, ['active' => 0]);
        return $this->success(null, 'Produk berhasil dihapus');
    }

    private function saveModifiers(string $productId, array $modifiers): void
    {
        $modModel = new ProductModifierModel();
        $optModel = new ModifierOptionModel();
        // Hapus modifier lama, insert baru
        $old = $modModel->where('product_id', $productId)->findAll();
        foreach ($old as $m) { $optModel->where('modifier_id',$m['id'])->delete(); }
        $modModel->where('product_id', $productId)->delete();

        foreach ($modifiers as $i => $mod) {
            $modId = generate_uuid();
            $modModel->insert(['id'=>$modId,'product_id'=>$productId,'name'=>$mod['name'],'type'=>$mod['type']??'single','required'=>$mod['required']??0,'sort_order'=>$i]);
            foreach ($mod['options'] ?? [] as $j => $opt) {
                $optModel->insert(['id'=>generate_uuid(),'modifier_id'=>$modId,'name'=>$opt['name'],'price_delta'=>$opt['price_delta']??0,'is_default'=>$opt['is_default']??0,'sort_order'=>$j]);
            }
        }
    }

    private function saveRecipe(string $productId, array $recipe): void
    {
        $recipeModel = new BomRecipeModel();
        $lineModel   = new BomRecipeLineModel();
        // Nonaktifkan resep lama
        $recipeModel->where('product_id',$productId)->set(['active'=>0])->update();

        $recipeId = generate_uuid();
        $recipeModel->insert(['id'=>$recipeId,'product_id'=>$productId,'name'=>$recipe['name']??'Default','active'=>1]);
        foreach ($recipe['lines'] ?? [] as $line) {
            $lineModel->insert(['id'=>generate_uuid(),'recipe_id'=>$recipeId,'material_id'=>$line['material_id'],'qty_required'=>$line['qty_required'],'notes'=>$line['notes']??null]);
        }
    }
}
