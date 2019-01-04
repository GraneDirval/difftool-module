<?php

namespace Playwing\DiffToolBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Playwing\DiffToolBundle\DiffTool\ChangesetMapper;
use Playwing\DiffToolBundle\DiffTool\DataComparator;
use Playwing\DiffToolBundle\DiffTool\EntitySerializationDataProvider;
use Playwing\DiffToolBundle\DiffTool\SQLGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDataDiffCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ChangesetMapper
     */
    private $changesetMapper;
    /**
     * @var DataComparator
     */
    private $diffComparator;
    /**
     * @var SQLGenerator
     */
    private $sqlGenerator;
    /**
     * @var string
     */
    private $cacheDir;
    /**
     * @var EntitySerializationDataProvider
     */
    private $dataProvider;

    public function __construct(
        EntityManagerInterface $entityManager,
        ChangesetMapper $changesetMapper,
        DataComparator $dataComparator,
        SQLGenerator $generator,
        string $cacheDir,
    EntitySerializationDataProvider $dataProvider
    )
    {
        parent::__construct(null);
        $this->entityManager   = $entityManager;
        $this->changesetMapper = $changesetMapper;
        $this->diffComparator  = $dataComparator;
        $this->sqlGenerator    = $generator;
        $this->cacheDir        = $cacheDir;
        $this->dataProvider = $dataProvider;
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('development:compare_diff_between_db_and_fixture')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Entities to be processed', null)
            ->addOption('dump-to-file', null, InputOption::VALUE_NONE, '', null);;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $mappings = $this->dataProvider->getEntityMappings($input->getOption('entity'));

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('softdeleteable')) {
            $filters->disable('softdeleteable');
        }

        $indexedDiffs = [];
        $ups          = [];
        $downs        = [];
        foreach ($mappings as $entity => $mapping) {


            $output->writeln(sprintf('Scanning %s...', $entity));

            list($diff, $addedToDB, $removedFromDB) = $this->diffComparator->compareDiffBetweenFixturesAndDB($mapping, $entity);


            if (count($diff)) {
                $output->writeln(sprintf('-- Changes in existing entries: %s', count($diff['diffs'])));
                $indexedDiffs[$diff['tableName']] = $diff;
            }

            if ($addedToDB) {
                $output->writeln(sprintf('-- Entries added: %s', count($addedToDB)));
            }
            if ($removedFromDB) {
                $output->writeln(sprintf('-- Entries removed: %s', count($removedFromDB)));
            }

            foreach ($removedFromDB as $uuid => $data) {
                $ups[]   = $this->sqlGenerator->createDeleteSQL($uuid, $data);
                $downs[] = $this->sqlGenerator->createInsertSQL($uuid, $data, $this->entityManager->getClassMetadata($entity));
            }
            foreach ($addedToDB as $uuid => $data) {
                $ups[]   = $this->sqlGenerator->createInsertSQL($uuid, $data, $this->entityManager->getClassMetadata($entity));
                $downs[] = $this->sqlGenerator->createDeleteSQL($uuid, $data);
            }

        }

        $output->writeln("Calculating changes...");
        $preparedChangesets = $this->changesetMapper->prepareChangesets($indexedDiffs);
        $output->writeln("Done.");

        list($upsForPropertyChanges, $downsForPropertyChanges) = $this->sqlGenerator->createUpdateSQLs($preparedChangesets);

        $ups   = array_merge($ups, $upsForPropertyChanges);
        $downs = array_merge($downs, $downsForPropertyChanges);


        if (!$input->getOption('dump-to-file')) {

            $upSqlRows   = implode(";\n\r", $ups);
            $downSqlRows = implode(";\n\r", $downs);

            $output->writeln('');
            $output->writeln("UP:");
            $output->writeln($upSqlRows);

            $output->writeln('');
            $output->writeln("DOWN:");
            $output->writeln($downSqlRows);
        } else {
            $upSqlRows   = implode(";\n", $ups);
            $downSqlRows = implode(";\n", $downs);

            $timestamp   = time();
            $destination = realpath($this->cacheDir . '/../../') . "/dump/data_diff_$timestamp.sql";

            $content = "UP:\n";
            $content .= $upSqlRows . "\n\n";
            $content .= "DOWN:\n";
            $content .= $downSqlRows . "\n\n";

            $output->writeln(sprintf('Dumping to file %s...', $destination));
            try {
                file_put_contents($destination, $content);
                $output->writeln('Done.');
            } catch (\Exception $exception) {
                $output->writeln("Error has been occurred during dumping: {$exception->getMessage()}");
            }

        }
    }


}


