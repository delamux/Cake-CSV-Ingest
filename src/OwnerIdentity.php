<?php
declare(strict_types=1);

namespace CsvIngest;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Who owns presets created in the current request.
 *
 * Uses `CsvIngest.ownerResolver` (Closure(ServerRequestInterface): string|int|null)
 * when configured, otherwise the `identity` request attribute set by
 * cakephp/authentication (`getIdentifier()`). Null means anonymous/shared.
 */
final class OwnerIdentity
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @return string|int|null
     */
    public static function fromRequest(ServerRequestInterface $request): string|int|null
    {
        $resolver = IngestSettings::get('ownerResolver');
        if ($resolver instanceof Closure) {
            $id = $resolver($request);
        } else {
            $identity = $request->getAttribute('identity');
            $id = is_object($identity) && method_exists($identity, 'getIdentifier') ? $identity->getIdentifier() : null;
        }

        return is_int($id) || (is_string($id) && $id !== '') ? $id : null;
    }
}
