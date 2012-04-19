<?php
class Tdcardsave_Module extends Core_ModuleBase
{
    /**
     * Creates the module information object
     * @return Core_ModuleInfo
     */
    protected function createModuleInfo()
    {
        return new Core_ModuleInfo(
            "Cardsave direct payment module",
            "Cardsave direct payment method",
            "Twin Dots" );
    }
}
