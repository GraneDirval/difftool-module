<?php

namespace Playwing\DiffToolBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Playwing\DiffToolBundle\DiffTool\DataComparator;
use Playwing\DiffToolBundle\DiffTool\EntityDataProvider;
use Playwing\DiffToolBundle\DiffTool\EntitySerializationDataProvider;
use Playwing\DiffToolBundle\DiffTool\EntitySerializer;
use Playwing\DiffToolBundle\DiffTool\FixtureDataLocator;
use Playwing\DiffToolBundle\Entity\Interfaces\HasUuid;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class UpdateFixturesFromV1Command extends ContainerAwareCommand
{
    /**
     * @var Filesystem
     */
    private $filesystem;
    private $rootDir;
    /**
     * @var EntityDataProvider
     */
    private $dataProvider;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EntitySerializer
     */
    private $entitySerializer;
    /**
     * @var DataComparator
     */
    private $dataComparator;
    /**
     * @var EntitySerializationDataProvider
     */
    private $serializationDataProvider;
    /**
     * @var FixtureDataLocator
     */
    private $locator;

    /**
     * UpdateFixturesFromV1Command constructor.
     * @param EntityDataProvider              $dataProvider
     * @param Filesystem                      $filesystem
     * @param                                 $rootDir
     * @param EntityManagerInterface          $manager
     * @param EntitySerializer                $entitySerializer
     * @param DataComparator                  $dataComparator
     * @param EntitySerializationDataProvider $serializationDataProvider
     * @param FixtureDataLocator              $locator
     */
    public function __construct(
        EntityDataProvider $dataProvider,
        Filesystem $filesystem,
        $rootDir,
        EntityManagerInterface $manager,
        EntitySerializer $entitySerializer,
        DataComparator $dataComparator,
        EntitySerializationDataProvider $serializationDataProvider,
        FixtureDataLocator $locator
    )
    {
        parent::__construct();

        $this->filesystem                = $filesystem;
        $this->rootDir                   = $rootDir;
        $this->dataProvider              = $dataProvider;
        $this->entityManager             = $manager;
        $this->entitySerializer          = $entitySerializer;
        $this->dataComparator            = $dataComparator;
        $this->serializationDataProvider = $serializationDataProvider;
        $this->locator                   = $locator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('development:update_fixtures')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Entities to be processed', null);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mappings = $this->serializationDataProvider->getEntityMappings($input->getOption('entity'));


        $filters = $this->entityManager->getFilters();
        if ($filters->has('softdeleteable')) {
            $filters->disable('softdeleteable');
        }

        foreach ($mappings as $entityClass => $config) {

            $interfaces = class_implements($entityClass);
            if (!isset($interfaces[HasUuid::class])) {
                throw new \Exception(sprintf('`%s` should implement `%s` interface', $entityClass, HasUuid::class));
            }

            $output->writeln(sprintf('Updating %s', $entityClass));

            @list($fileName, $ignoredFields) = @$config;

            if (!is_array($ignoredFields)) {
                $ignoredFields = [];
            }
            $result = $this->dataProvider->getEntityData($entityClass, $ignoredFields);

            /** @var ClassMetadata $metadata */
            $classMetadataFactory = $this->entityManager->getMetadataFactory();
            $metadata             = $classMetadataFactory->getMetadataFor($entityClass);

            $output->writeln(sprintf('Found %s entries', count($result)));

            $newFixture = [];
            foreach ($result as $entity) {
                $serializedEntity = $this->entitySerializer->serializeEntity($metadata, $entity, $classMetadataFactory);
                $newFixture[]     = $serializedEntity;
            }


            $destination = $this->locator->getDefaultPathToWrite().$fileName;
            $output->writeln(sprintf('Creating %s', $fileName));

            if (!$this->filesystem->exists($destination)) {
                $this->filesystem->touch($destination);
                $this->putFixtureToFile($destination, $newFixture);

            } else {
                $oldFixture = json_decode(file_get_contents($destination), true);
                $oldFixture = $this->applyChangesFromNewFixture($newFixture, $oldFixture);

                $this->putFixtureToFile($destination, $oldFixture);

            }

            $output->writeln('Done');
        }
    }

    private function applyDiffToFixtureRecursive($diff, $row)
    {
        foreach ($diff as $propertyName => $changeset) {
            if ($propertyName == 'id') {
                continue;
            }

            if (is_array($changeset) &&
                (array_key_exists(0, $changeset)) &&
                (array_key_exists(1, $changeset))
            ) {
                $row[$propertyName] = $changeset[1];
            } else if (is_array($changeset)) {
                $row[$propertyName] = $this->applyDiffToFixtureRecursive($changeset, $row[$propertyName]);
            }
        }
        return $row;
    }

    /**
     * @param $destination
     * @param $newFixture
     */
    protected function putFixtureToFile($destination, $newFixture)
    {
        file_put_contents($destination, json_encode(array_values($newFixture), JSON_PRETTY_PRINT));
    }

    /**
     * @param $newFixture
     * @param $oldFixture
     * @return array
     */
    private function applyChangesFromNewFixture($newFixture, $oldFixture): array
    {
        list($diffs, $addedEntries, $deletedEntries) = $this->dataComparator->compareDiffBetweenTwoFixtures($newFixture, $oldFixture);

        foreach ($oldFixture as $i => $row) {
            $uuid = $row['uuid'];
            if (isset($deletedEntries[$uuid])) {
                unset($oldFixture[$i]);
                continue;
            }

            if (isset($diffs[$uuid])) {
                $oldFixture[$i] = $this->applyDiffToFixtureRecursive($diffs[$uuid], $row);
            }
        }

        foreach ($addedEntries as $addedEntry) {
            $oldFixture[] = $addedEntry;
        }
        return $oldFixture;
    }
}