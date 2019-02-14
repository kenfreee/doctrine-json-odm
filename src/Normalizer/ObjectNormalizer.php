<?php

/*
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Dunglas\DoctrineJsonOdm\Normalizer;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Transforms an object to an array with the following keys:
 * * _type: the class name
 * * _value: a representation of the values of the object.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class ObjectNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var NormalizerInterface|DenormalizerInterface
     */
    private $serializer;
    private $objectNormalizer;

    public function __construct(NormalizerInterface $objectNormalizer)
    {
        if (!$objectNormalizer instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(sprintf('The normalizer used must implement the "%s" interface.', DenormalizerInterface::class));
        }

        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     * @throws \ReflectionException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $reflectionClass = new \ReflectionClass($object);

        $data = [];
        foreach ($reflectionClass->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }

            $data[$property->getName()] = $this->getNormalizedValue($object->{$property->getName()}, $format, $context);
        }

        return $this->associateTypeWithData($object, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data);
    }

    /**
     * {@inheritdoc}
     * @throws \ReflectionException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (\is_object($data)) {
            return $data;
        }

        if (isset($data['#type'])) {
            $type = $data['#type'];
            unset($data['#type']);

            $data = $this->denormalize($data, $type, $format, $context);

            if (!\class_exists($type)) {
                return $data;
            }

            $reflectionClass = new \ReflectionClass($type);
            $object = $reflectionClass->newInstance();

            foreach ($data as $propertyName => $propertyValue) {
                if ($reflectionClass->hasProperty($propertyName)) {
                    $reflectionProperty = new \ReflectionProperty($type, $propertyName);
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($object, $propertyValue);
                }
            }

            return $object;
        }

        if (\is_array($data) || $data instanceof \Traversable) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->serializer->denormalize($value, $value['#type'] ?? $class, $format, $context);
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(
                sprintf('The injected serializer must implement "%s" and "%s".', NormalizerInterface::class, DenormalizerInterface::class)
            );
        }

        $this->serializer = $serializer;

        if ($this->objectNormalizer instanceof SerializerAwareInterface) {
            $this->objectNormalizer->setSerializer($serializer);
        }
    }

    /**
     * @param mixed $object
     * @param mixed $data
     * @return array
     */
    private function associateTypeWithData($object, $data)
    {
        return \array_merge(['#type' => ClassUtils::getClass($object)], $data);
    }

    /**
     * @param mixed $value
     * @param string|null $format
     * @param array $context
     * @return mixed
     */
    private function getNormalizedValue($value, string $format = null, array $context = [])
    {
        if (\is_object($value)) {
            return $this->associateTypeWithData($value, $this->serializer->normalize($value, $format, $context));
        }

        if (\is_array($value) || $value instanceof \Traversable) {
            return \array_map(function($item) use ($format, $context) {
                return $this->getNormalizedValue($item, $format, $context);
            }, $value);
        }

        return $value;
    }
}
