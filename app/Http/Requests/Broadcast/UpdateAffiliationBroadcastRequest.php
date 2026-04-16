<?php

namespace App\Http\Requests\Broadcast;

class UpdateAffiliationBroadcastRequest extends AffiliationBroadcastRequest
{
    public function rules(): array
    {
        return $this->baseRules(true);
    }
}
