<?php
namespace CedricBlondeau\CatalogImportCommand\Model;

use Magento\ImportExport\Model\Import as MagentoImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class Import
 * @package CedricBlondeau\CatalogImportCommand\Model
 */
class Import
{
    /**
     * @var \Magento\ImportExport\Model\Import
     */
    private $importModel;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $readFactory;

    /**
     * @var \Magento\ImportExport\Model\Import\Source\CsvFactory
     */
    private $csvSourceFactory;

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;

    /**
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\ImportExport\Model\Import $importModel
     * @param \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     */
    public function __construct(
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\ImportExport\Model\Import $importModel,
        \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->readFactory = $readFactory;
        $importModel->setData(
            [
                'entity' => 'catalog_product',
                'behavior' => MagentoImport::BEHAVIOR_APPEND,
                MagentoImport::FIELD_NAME_IMG_FILE_DIR => 'pub/media/catalog/product',
                MagentoImport::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS
            ]
        );
        $this->importModel = $importModel;
    }

    /**
     * @param $fileName
     */
    public function setFile($fileName)
    {
        if (!file_exists($fileName)) {
            throw new FileNotFoundException();
        }
        $validate = $this->importModel->validateSource($this->csvSourceFactory->create(
            [
                'file' => $fileName,
                'directory' => $this->readFactory->create(getcwd())
            ]
        ));
        if (!$validate) {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @param $imagesPath
     */
    public function setImagesPath($imagesPath)
    {
        $this->importModel->setData(MagentoImport::FIELD_NAME_IMG_FILE_DIR, $imagesPath);
    }

    /**
     * @param $behavior
     */
    public function setBehavior($behavior)
    {
        if (in_array($behavior, array(
            MagentoImport::BEHAVIOR_APPEND,
            MagentoImport::BEHAVIOR_ADD_UPDATE,
            MagentoImport::BEHAVIOR_REPLACE,
            MagentoImport::BEHAVIOR_DELETE
        ))) {
            $this->importModel->setData('behavior', $behavior);
        }
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $this->eavConfig->clear();
        $result = $this->importModel->importSource();
        $this->eavConfig->clear();
        $this->reindex();
        return $result;
    }

    /**
     * @return string
     * @internal yep, there is a typo here, see https://github.com/magento/magento2/pull/2771
     */
    public function getFormattedLogTrace()
    {
        return $this->importModel->getFormatedLogTrace();
    }

    /**
     * Perform full reindex
     */
    private function reindex()
    {
        foreach ($this->indexerCollectionFactory->create()->getItems() as $indexer) {
            $indexer->reindexAll();
        }
    }
}
