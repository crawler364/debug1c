<?php

global $USER;
if ($USER->IsAuthorized()) {
    $arResult['USER_INFO'] = CUser::GetByID($USER->GetID())->Fetch();
    $arResult['USER_INFO']['IS_ADMIN'] = $USER->IsAdmin();
}
