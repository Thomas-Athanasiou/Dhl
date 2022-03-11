<?php
    /**
     * @author Thomas Athanasiou at Hippiemonkeys | @Thomas-Athanasiou
     * @copyright Copyright (c) 2022 Hippiemonkeys (https://hippiemonkeys.com)
     * @package Hippiemonkeys_Dhl
     */

    namespace Hippiemonkeys\Dhl\Model\Config\Source;

    use Magento\Framework\Option\ArrayInterface,
        Magento\Dhl\Model\Carrier as MagentoCarrier,
        Hippiemonkeys\Dhl\Model\Carrier as HippiemonkeysCarrier;

    class Model
    implements ArrayInterface
    {
        /**
         * @inheritdoc
         */
        public function toOptionArray()
        {
            return [
                MagentoCarrier::class       => __('Native'),
                HippiemonkeysCarrier::class => __('Hippiemonkeys')
            ];
        }
    }
?>