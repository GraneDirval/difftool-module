<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 19.11.18
 * Time: 18:57
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Proxy\Proxy;
use ProxyManager\Proxy\ProxyInterface;

class EntitySerializer
{
    /**
     * @param $entityMetadata
     * @param $entity
     * @param $classMetadataFactory
     * @return array
     */
    public function serializeEntity(ClassMetadata $entityMetadata, $entity, \Doctrine\Common\Persistence\Mapping\ClassMetadataFactory $classMetadataFactory): array
    {
        $serializedEntity = [];
        /** @var \ReflectionProperty[] $properties */
        $properties = $entityMetadata->getReflectionProperties();
        foreach ($properties as $property) {

            $property->setAccessible(true);
            $value = $property->getValue($entity);
            $name  = $property->getName();


            if ($value instanceof PersistentCollection) {

                $associationMapping = $value->getMapping();
                if ($associationMapping['type'] !== ClassMetadata::MANY_TO_MANY) {
                    continue;
                }

                /** @var ClassMetadata $targetEntityMetadata */
                $targetEntityMetadata    = $classMetadataFactory->getMetadataFor($associationMapping['targetEntity']);
                $serializedEntity[$name] = $value->map(function ($entity) use ($targetEntityMetadata) {

                    if ($targetEntityMetadata->hasField('uuid')) {
                        $uuidProperty = $targetEntityMetadata->getReflectionProperty('uuid');
                        $uuidProperty->setAccessible(true);
                        return ['uuid' => $uuidProperty->getValue($entity)];
                    } else {
                        return $targetEntityMetadata->getIdentifierValues($entity);
                    }

                })->toArray();

            } else if (is_object($value)) {

                try {
                    /** @var ClassMetadata $associationMetadata */
                    $associationMetadata = $classMetadataFactory->getMetadataFor(get_class($value));
                } catch (\Doctrine\Common\Persistence\Mapping\MappingException $exception) {
                    $associationMetadata = null;
                }

                if ($associationMetadata) {
                    /** @var ClassMetadata $entityMetadata */

                    if ($associationMetadata->hasField('uuid')) {
                        /** @var ProxyInterface $value */
                        if ($value instanceof Proxy) {
                            if (!$value->__isInitialized()) {
                                $value->__load();
                            }
                        }
                        $uuidProperty = $associationMetadata->getReflectionProperty('uuid');
                        $uuidProperty->setAccessible(true);
                        $value = ['uuid' => $uuidProperty->getValue($value)];
                    } else {
                        $value = $associationMetadata->getIdentifierValues($value);
                    }

                } else if ($value instanceof \DateTimeInterface) {


                    if ($this->isDateCorrect($value)) {
                        $typeOfField   = $entityMetadata->getTypeOfField($name);
                        $format = ($typeOfField == Type::DATE)
                            ? 'Y-m-d'
                            : DATE_RFC3339;
                        $value  = $value->format($format);
                    } else {
                        $value = null;
                    }

                }
                $serializedEntity[$name] = $value;
            } else {
                $serializedEntity[$name] = $value;
            }

        }
        return $serializedEntity;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function isDateCorrect(\DateTimeInterface $value): bool
    {
        return ((string)$value->format(DATE_RFC3339))[0] !== '-';
    }

}