<?php
namespace App\Controllers\Api;
use App\Models\CategoryModel;

class CategoryController extends BaseApiController
{
    protected CategoryModel $model;

    public function __construct()
    {
        helper(['uuid','code']);
        $this->model = new CategoryModel();
    }

    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page') ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 50);

        $total = $this->model->forOutlet($outletId)->where('active',1)->countAllResults(false);
        $data  = $this->model->forOutlet($outletId)->where('active',1)
                             ->orderBy('sort_order','ASC')->orderBy('name','ASC')
                             ->paginate($perPage, 'default', $page);

        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    public function show($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Kategori tidak ditemukan');
        return $this->success($row);
    }

    public function create()
    {
        $json = $this->request->getJSON(true) ?? [];
        $rules = ['name' => 'required|max_length[100]'];
        if (!$this->validate($rules)) return $this->error('Validasi gagal', 422, $this->validator->getErrors());

        $outletId = $this->currentOutletId();
        $data = [
            'id'         => generate_uuid(),
            'code'       => generate_code('categories', $outletId),
            'outlet_id'  => $outletId,
            'name'       => $json['name'],
            'sort_order' => $json['sort_order'] ?? 0,
            'active'     => 1,
        ];
        $this->model->insert($data);
        return $this->created($data, 'Kategori berhasil dibuat');
    }

    public function update($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Kategori tidak ditemukan');

        $json = $this->request->getJSON(true) ?? [];
        $allowed = ['name','sort_order','active'];
        $data = array_intersect_key($json, array_flip($allowed));
        $this->model->update($id, $data);
        return $this->success(array_merge($row, $data), 'Kategori berhasil diupdate');
    }

    public function delete($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Kategori tidak ditemukan');
        $this->model->update($id, ['active' => 0]);
        return $this->success(null, 'Kategori berhasil dihapus');
    }
}
