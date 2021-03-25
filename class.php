<?php

class WCDebug1C extends CBitrixComponent
{
    public function executeComponent()
    {
        \CUtil::InitJSCore(['ajax']);

        $this->includeComponentTemplate();
    }
}
