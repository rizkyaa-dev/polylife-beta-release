<?php

namespace App\Http\Requests\Broadcast;

class StoreAffiliationBroadcastRequest extends AffiliationBroadcastRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
