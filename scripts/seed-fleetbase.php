<?php
/**
 * Seed a local Fleetbase instance with a company, API keys and fleet data for plugin development.
 * Run inside the Fleetbase API container via `php artisan tinker scripts/seed-fleetbase.php`
 * (scripts/seed-fleetbase.sh does this for you). Idempotent: re-running updates nothing it already made.
 */

use Fleetbase\Models\ApiCredential;
use Fleetbase\Models\Company;
use Fleetbase\Models\CompanyUser;
use Fleetbase\Models\User;

$ownerEmail  = getenv( 'FBWC_SEED_OWNER' ) ?: 'ron@fleetbase.io';
$companyName = getenv( 'FBWC_SEED_COMPANY' ) ?: 'Woo Test Store';

$owner = User::where( 'email', $ownerEmail )->first();
if ( ! $owner ) {
	fwrite( STDERR, "Owner user {$ownerEmail} not found\n" );
	return;
}

$company = Company::where( 'name', $companyName )->first();
if ( ! $company ) {
	$company = Company::create(
		[
			'name'                    => $companyName,
			'owner_uuid'              => $owner->uuid,
			'currency'                => 'USD',
			'country'                 => 'US',
			'timezone'                => 'America/Los_Angeles',
			'onboarding_completed_at' => now(),
			'status'                  => 'active',
		]
	);
	echo "Created company {$company->uuid}\n";
} else {
	echo "Company exists {$company->uuid}\n";
}

if ( ! CompanyUser::where( [ 'company_uuid' => $company->uuid, 'user_uuid' => $owner->uuid ] )->exists() ) {
	CompanyUser::create( [ 'company_uuid' => $company->uuid, 'user_uuid' => $owner->uuid, 'status' => 'active' ] );
}

foreach ( [ 'live' => false, 'test' => true ] as $label => $testMode ) {
	$name = "WooCommerce plugin ({$label})";
	$cred = ApiCredential::where( [ 'company_uuid' => $company->uuid, 'name' => $name ] )->whereNull( 'deleted_at' )->first();
	if ( ! $cred ) {
		$cred = ApiCredential::create(
			[
				'user_uuid'    => $owner->uuid,
				'company_uuid' => $company->uuid,
				'name'         => $name,
			]
		);
	}
	// The observer generates keys on create from test_mode, which is not mass assignable,
	// so set the mode and matching key directly.
	$expectedPrefix = $testMode ? 'flb_test_' : 'flb_live_';
	if ( (int) $cred->test_mode !== (int) $testMode || ! str_starts_with( (string) $cred->key, $expectedPrefix ) ) {
		$keys = ApiCredential::generateKeys( null, $testMode );
		\Illuminate\Support\Facades\DB::table( 'api_credentials' )->where( 'uuid', $cred->uuid )->update(
			[ 'test_mode' => $testMode ? 1 : 0, 'key' => $keys['key'], 'secret' => $keys['secret'] ]
		);
		$cred = $cred->fresh();
	}
	echo strtoupper( $label ) . "_KEY={$cred->key}\n";
}
