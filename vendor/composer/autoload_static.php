<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8a393a51e6af0488a98e471d1268aa2a
{
    public static $files = array (
        '256558b1ddf2fa4366ea7d7602798dd1' => __DIR__ . '/..' . '/yahnis-elsts/plugin-update-checker/load-v5p5.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Y' => 
        array (
            'YourNamespace\\WordPressAuditPlugin\\' => 35,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'YourNamespace\\WordPressAuditPlugin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8a393a51e6af0488a98e471d1268aa2a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8a393a51e6af0488a98e471d1268aa2a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8a393a51e6af0488a98e471d1268aa2a::$classMap;

        }, null, ClassLoader::class);
    }
}
