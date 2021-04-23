<?php

class WCDebug1C extends CBitrixComponent
{
    protected function listKeysSignedParameters(): array
    {
        return ['PASSWORD', 'LOGIN'];
    }

    public function executeComponent()
    {
        CUtil::InitJSCore(['ajax']);

        $this->includeComponentTemplate();
    }
}
