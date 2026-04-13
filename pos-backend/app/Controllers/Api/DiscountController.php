<?php
namespace App\Controllers\Api;
use App\Models\DiscountModel;
use App\Models\DiscountRuleModel;
use App\Models\DiscountTargetModel;
use App\Libraries\DiscountEngine;

class DiscountController extends BaseApiController
{
    protected DiscountModel $model;

    public function __construct()
    {
        helper(['uuid','code']);
        $this->model = new DiscountModel();
    }

    public function index()
    {
        $outletId = $this->currentOutletId();
        $page     = (int)($this->request->getGet('page') ?? 1);
        $perPage  = (int)($this->request->getGet('per_page') ?? 50);
        $total    = $this->model->forOutlet($outletId)->countAllResults(false);
        $data     = $this->model->forOutlet($outletId)->orderBy('priority','DESC')->paginate($perPage,'default',$page);
        return $this->success($this->paginate($data, $total, $page, $perPage));
    }

    public function show($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Diskon tidak ditemukan');
        $row['rules']   = (new DiscountRuleModel())->getByDiscount($id);
        $row['targets'] = (new DiscountTargetModel())->where('discount_id',$id)->findAll();
        return $this->success($row);
    }

    public function create()
    {
        $json  = $this->request->getJSON(true) ?? [];
        $rules = ['name' => 'required|max_length[150]', 'type' => 'required', 'value' => 'required|numeric'];
        if (!$this->validate($rules)) return $this->error('Validasi gagal', 422, $this->validator->getErrors());

        $outletId = $this->currentOutletId();
        $id       = generate_uuid();
        $data = [
            'id'                => $id,
            'code_internal'     => generate_code('discounts', $outletId),
            'outlet_id'         => $outletId,
            'name'              => $json['name'],
            'code'              => $json['code'] ?? null,
            'type'              => $json['type'],
            'value'             => $json['value'],
            'max_cap'           => $json['max_cap'] ?? null,
            'is_stackable'      => $json['is_stackable'] ?? 0,
            'priority'          => $json['priority'] ?? 0,
            'usage_limit'       => $json['usage_limit'] ?? null,
            'require_member'    => $json['require_member'] ?? 0,
            'min_member_tier'   => $json['min_member_tier'] ?? null,
            'active'            => $json['active'] ?? 1,
            'valid_from'        => $json['valid_from'] ?? null,
            'valid_until'       => $json['valid_until'] ?? null,
            'created_by'        => $this->currentUserId(),
        ];
        $this->model->insert($data);

        if (!empty($json['rules']))   $this->saveRules($id, $json['rules']);
        if (!empty($json['targets'])) $this->saveTargets($id, $json['targets']);

        return $this->created($data, 'Diskon berhasil dibuat');
    }

    public function update($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Diskon tidak ditemukan');

        $json    = $this->request->getJSON(true) ?? [];
        $allowed = ['name','code','type','value','max_cap','is_stackable','priority',
                    'usage_limit','require_member','min_member_tier','active','valid_from','valid_until'];
        $data    = array_intersect_key($json, array_flip($allowed));
        $this->model->update($id, $data);

        if (isset($json['rules']))   $this->saveRules($id, $json['rules']);
        if (isset($json['targets'])) $this->saveTargets($id, $json['targets']);

        return $this->success(array_merge($row, $data), 'Diskon berhasil diupdate');
    }

    public function delete($id)
    {
        $row = $this->model->forOutlet($this->currentOutletId())->find($id);
        if (!$row) return $this->notFound('Diskon tidak ditemukan');
        $this->model->update($id, ['active' => 0]);
        return $this->success(null, 'Diskon berhasil dinonaktifkan');
    }

    // POST /api/v1/discounts/validate — cek voucher code saja
    public function validate()
    {
        $json = $this->request->getJSON(true) ?? [];
        $code = $json['code'] ?? '';
        if (!$code) return $this->error('Kode voucher tidak boleh kosong');

        $disc = $this->model->findByCode($code, $this->currentOutletId());
        if (!$disc) return $this->error('Kode voucher tidak valid atau sudah kadaluarsa', 404);

        return $this->success([
            'discount_id'   => $disc['id'],
            'name'          => $disc['name'],
            'type'          => $disc['type'],
            'value'         => $disc['value'],
            'is_stackable'  => $disc['is_stackable'],
        ], 'Voucher valid');
    }

    // POST /api/v1/discounts/calculate — preview kalkulasi diskon
    public function calculate()
    {
        $json     = $this->request->getJSON(true) ?? [];
        $items    = $json['items'] ?? [];
        $subtotal = (float)($json['subtotal'] ?? 0);
        $voucher  = $json['voucher_code'] ?? null;
        $method   = $json['payment_method'] ?? 'cash';
        $custId   = $json['customer_id'] ?? null;

        $engine = new DiscountEngine($this->currentOutletId(), $custId);
        $result = $engine->calculate($items, $subtotal, $voucher, $method);

        return $this->success($result);
    }

    private function saveRules(string $discountId, array $rules): void
    {
        $model = new DiscountRuleModel();
        $model->where('discount_id', $discountId)->delete();
        foreach ($rules as $rule) {
            $model->insert([
                'id'          => generate_uuid(),
                'discount_id' => $discountId,
                'rule_type'   => $rule['rule_type'],
                'rule_value'  => is_array($rule['rule_value']) ? json_encode($rule['rule_value']) : $rule['rule_value'],
            ]);
        }
    }

    private function saveTargets(string $discountId, array $targets): void
    {
        $model = new DiscountTargetModel();
        $model->where('discount_id', $discountId)->delete();
        foreach ($targets as $t) {
            $model->insert([
                'id'          => generate_uuid(),
                'discount_id' => $discountId,
                'target_type' => $t['target_type'] ?? 'order',
                'target_id'   => $t['target_id'] ?? null,
            ]);
        }
    }
}
