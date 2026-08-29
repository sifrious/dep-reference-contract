<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Sifrious\ReferenceContract\CrossPackageReference;
use Sifrious\ReferenceContract\IncompleteReferenceResolution;
use Sifrious\ReferenceContract\ReferenceAccess;
use Sifrious\ReferenceContract\ReferenceBatch;
use Sifrious\ReferenceContract\ReferenceDirectory;
use Sifrious\ReferenceContract\ReferenceOwnerResolver;
use Sifrious\ReferenceContract\ReferenceResolution;
use Sifrious\ReferenceContract\ReferenceResolutionSet;
use Sifrious\ReferenceContract\ReferenceResolutionStatus;
use Sifrious\ReferenceContract\ReferenceSnapshot;

function check(bool $condition, string $message): void { if (! $condition) { throw new RuntimeException($message); } }
function fixture(string $name, Closure $test): void { $test(); fwrite(STDOUT, "PASS {$name}\n"); }

fixture('v1 nested provenance and JSON queue round trip', function (): void {
    $reference = new CrossPackageReference('sifrious/aleph', 'observation', 'obs_01', 'sha256:abc', new CrossPackageReference('sifrious/funes', 'provenance', 'prov_01'));
    $expected = ['contract'=>'sifrious.cross-package-reference','contract_version'=>1,'owner'=>'sifrious/aleph','type'=>'observation','id'=>'obs_01','object_version'=>'sha256:abc','provenance'=>['contract'=>'sifrious.cross-package-reference','contract_version'=>1,'owner'=>'sifrious/funes','type'=>'provenance','id'=>'prov_01','object_version'=>null,'provenance'=>null]];
    check($reference->toArray() === $expected, 'Funes v1 serialization changed.');
    $queued = json_decode(json_encode($reference, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $restored = CrossPackageReference::fromArray($queued);
    check($restored->equals($reference) && $restored->key() === $reference->key(), 'Queue round trip was not lossless.');
});

fixture('explicit batch resolution states and owning-package authorization', function (): void {
    $available = new CrossPackageReference('sifrious/owner', 'record', 'available');
    $deleted = new CrossPackageReference('sifrious/owner', 'record', 'deleted');
    $old = new CrossPackageReference('sifrious/owner', 'record', 'old');
    $secret = new CrossPackageReference('sifrious/owner', 'record', 'secret');
    $missingOwner = new CrossPackageReference('sifrious/missing', 'record', 'none');
    $replacement = new CrossPackageReference('sifrious/owner', 'record', 'new');
    $resolver = new class($available, $deleted, $old, $secret, $replacement) implements ReferenceOwnerResolver {
        public function __construct(private CrossPackageReference $available, private CrossPackageReference $deleted, private CrossPackageReference $old, private CrossPackageReference $secret, private CrossPackageReference $replacement) {}
        public function owner(): string { return 'sifrious/owner'; }
        public function resolveBatch(ReferenceBatch $batch, ReferenceAccess $access): ReferenceResolutionSet {
            return new ReferenceResolutionSet(array_map(function (CrossPackageReference $reference) use ($access): ReferenceResolution {
                if ($reference->equals($this->secret)) { return ReferenceResolution::unauthorized($reference); }
                if ($reference->equals($this->deleted)) { return ReferenceResolution::tombstoned($reference, new ReferenceSnapshot($reference, 'Deleted display snapshot')); }
                if ($reference->equals($this->old)) { return ReferenceResolution::superseded($reference, $this->replacement); }
                return ($access->claims['tenant'] ?? null) === 'tenant-a' ? ReferenceResolution::available(new ReferenceSnapshot($reference, 'Visible')) : ReferenceResolution::unauthorized($reference);
            }, $batch->references));
        }
    };
    $results = (new ReferenceDirectory([$resolver]))->resolveBatch(new ReferenceBatch([$available, $deleted, $old, $secret, $missingOwner]), new ReferenceAccess(new CrossPackageReference('sifrious/accounts', 'user', 'user-a'), ['tenant'=>'tenant-a']));
    check($results->get($available)->status === ReferenceResolutionStatus::Available, 'Available state missing.');
    check($results->get($deleted)->status === ReferenceResolutionStatus::Tombstoned, 'Tombstone state missing.');
    check($results->get($old)->status === ReferenceResolutionStatus::Superseded, 'Superseded state missing.');
    check($results->get($secret)->status === ReferenceResolutionStatus::Unauthorized, 'Unauthorized state missing.');
    check($results->get($missingOwner)->status === ReferenceResolutionStatus::Unavailable, 'Unavailable state missing.');
});

fixture('incomplete owner result fails closed', function (): void {
    $resolver = new class implements ReferenceOwnerResolver {
        public function owner(): string { return 'sifrious/owner'; }
        public function resolveBatch(ReferenceBatch $batch, ReferenceAccess $access): ReferenceResolutionSet { return new ReferenceResolutionSet([]); }
    };
    try {
        (new ReferenceDirectory([$resolver]))->resolveBatch(new ReferenceBatch([new CrossPackageReference('sifrious/owner', 'record', 'one')]), new ReferenceAccess(new CrossPackageReference('sifrious/accounts', 'user', 'user-a')));
        throw new RuntimeException('Incomplete resolution unexpectedly passed.');
    } catch (IncompleteReferenceResolution) {}
});
