<?php

class Debug1C extends CBitrixComponent
{
    public function __construct($component = null)
    {
        parent::__construct($component);

        \CUtil::InitJSCore(['ajax']);
    }

    protected function listKeysSignedParameters(): array
    {
        return ['PASSWORD', 'LOGIN'];
    }

    public function executeComponent()
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            $this->arResult['USER_INFO'] = CUser::GetByID($USER->GetID())->Fetch();
            $this->arResult['USER_INFO']['IS_AUTHORIZED'] = true;
            $this->arResult['USER_INFO']['IS_ADMIN'] = $USER->IsAdmin();
        }

        $this->includeComponentTemplate();
    }
}
