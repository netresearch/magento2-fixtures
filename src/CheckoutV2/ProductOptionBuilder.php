<?php

namespace TddWizard\Fixtures\CheckoutV2;

use Magento\Catalog\Api\Data\CustomOptionInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionExtensionInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionInterface;
use Magento\Quote\Api\Data\ProductOptionInterfaceFactory;

/**
 * Builder to create quote item product options.
 *
 * Currently, only custom options are supported. Other types may be added in the future:
 * - bundle options
 * - downloadable options
 * - configurable item options
 * - gift card options
 */
class ProductOptionBuilder
{
    /**
     * @var ProductOptionInterfaceFactory
     */
    private $productOptionFactory;

    /**
     * @var ProductOptionExtensionInterfaceFactory
     */
    private $productOptionExtensionFactory;

    /**
     * @var CustomOptionInterfaceFactory
     */
    private $customOptionFactory;

    /**
     * @var string[]
     */
    private $customOptions = [];

    /**
     * ProductOptionBuilder constructor.
     * @param ProductOptionInterfaceFactory $productOptionFactory
     * @param ProductOptionExtensionInterfaceFactory $productOptionExtensionFactory
     * @param CustomOptionInterfaceFactory $customOptionFactory
     */
    public function __construct(
        ProductOptionInterfaceFactory $productOptionFactory,
        ProductOptionExtensionInterfaceFactory $productOptionExtensionFactory,
        CustomOptionInterfaceFactory $customOptionFactory
    ) {
        $this->productOptionFactory = $productOptionFactory;
        $this->productOptionExtensionFactory = $productOptionExtensionFactory;
        $this->customOptionFactory = $customOptionFactory;
    }

    /**
     * @param string $optionId
     * @param string $optionValue
     */
    public function addCustomOption($optionId, $optionValue)
    {
        $this->customOptions[$optionId] = $optionValue;
    }

    /**
     * @return ProductOptionInterface
     */
    public function build()
    {
        $productOption = $this->productOptionFactory->create();
        if (empty($this->customOptions)) {
            return $productOption;
        }

        $productOptionExtension = $this->productOptionExtensionFactory->create();

        $customOptions = [];
        foreach ($this->customOptions as $optionId => $optionValue) {
            $customOption = $this->customOptionFactory->create();
            $customOption->setOptionId($optionId);
            $customOption->setOptionValue($optionValue);
            $customOptions[] = $customOption;
        }

        $productOptionExtension->setCustomOptions($customOptions);
        $productOption->setExtensionAttributes($productOptionExtension);

        $this->customOptions = [];

        return $productOption;
    }
}
