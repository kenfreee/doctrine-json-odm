<?php

namespace Dunglas\DoctrineJsonOdm\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SerializerPass implements CompilerPassInterface
{
    private const NATIVE_SERIALIZER_SERVICE_ID = 'serializer';
    private const CUSTOM_SERIALIZER_SERVICE_ID = 'dunglas_doctrine_json_odm.serializer';
    private const EXCLUDED_NORMALIZER_REFERENCES = [
        'serializer.normalizer.object',
    ];

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(self::NATIVE_SERIALIZER_SERVICE_ID) || !$container->hasDefinition(self::CUSTOM_SERIALIZER_SERVICE_ID)) {
            return;
        }

        $nativeNormalizerRefs = $container->getDefinition(self::NATIVE_SERIALIZER_SERVICE_ID)->getArgument(0);

        $nativeNormalizerRefs = \array_filter($nativeNormalizerRefs, function(Reference $normalizerRef) {
            return !\in_array($normalizerRef->__toString(), self::EXCLUDED_NORMALIZER_REFERENCES);
        });

        $serializerDefinition = $container->getDefinition(self::CUSTOM_SERIALIZER_SERVICE_ID);

        $normalizerRefs = $serializerDefinition->getArgument(0);

        if (0 === \count($normalizerRefs) || 0 === \count($nativeNormalizerRefs)) {
            return;
        }

        $serializerDefinition->replaceArgument(0, \array_merge($nativeNormalizerRefs, $normalizerRefs));
    }
}
