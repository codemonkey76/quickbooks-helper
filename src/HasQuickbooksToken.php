<?php

namespace Codemonkey76\Quickbooks;

trait HasQuickbooksToken
{
    public function quickbooksToken()
    {
        return $this->hasOne(Token::class);
    }
}