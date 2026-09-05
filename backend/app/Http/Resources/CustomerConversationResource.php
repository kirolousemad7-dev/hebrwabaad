<?php

namespace App\Http\Resources;

class CustomerConversationResource extends ConversationResource
{
    public function __construct($resource)
    {
        parent::__construct($resource, true);
    }
}
