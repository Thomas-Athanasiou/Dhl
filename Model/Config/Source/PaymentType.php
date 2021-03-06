<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou at Hippiemonkeys
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2022 Hippiemonkeys Web Inteligence EE (https://hippiemonkeys.com)
     * @package Hippiemonkeys_Dhl
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\Dhl\Model\Config\Source;

    use Magento\Framework\Option\ArrayInterface,
        Hippiemonkeys\Dhl\Model\Carrier;

    class PaymentType
    implements ArrayInterface
    {
        /**
         * @inheritdoc
         */
        public function toOptionArray()
        {
            return [
                Carrier::PAYMENT_TYPE_SHIPPER       => __('Shipper'),
                Carrier::PAYMENT_TYPE_RECEIVER      => __('Receiver'),
                Carrier::PAYMENT_TYPE_THIRDPARTY    => __('Third Party')
            ];
        }
    }
?>