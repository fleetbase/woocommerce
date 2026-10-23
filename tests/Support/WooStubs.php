<?php
/**
 * Minimal stand-ins for WooCommerce classes so pure mapping logic can be unit tested.
 * Loaded only when WooCommerce is not present.
 */

declare(strict_types=1);

// phpcs:ignoreFile

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function __construct( private array $d ) {}
		public function is_virtual(): bool { return (bool) ( $this->d['virtual'] ?? false ); }
		public function get_id(): int { return (int) ( $this->d['id'] ?? 0 ); }
		public function get_sku(): string { return (string) ( $this->d['sku'] ?? '' ); }
		public function get_weight(): string { return (string) ( $this->d['weight'] ?? '' ); }
		public function get_length(): string { return (string) ( $this->d['length'] ?? '' ); }
		public function get_width(): string { return (string) ( $this->d['width'] ?? '' ); }
		public function get_height(): string { return (string) ( $this->d['height'] ?? '' ); }
		public function get_short_description(): string { return (string) ( $this->d['short_description'] ?? '' ); }
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	class WC_Order_Item_Product {
		public function __construct( private array $d ) {}
		public function get_product(): ?WC_Product { return $this->d['product'] ?? null; }
		public function get_quantity(): int { return (int) ( $this->d['quantity'] ?? 1 ); }
		public function get_name(): string { return (string) ( $this->d['name'] ?? '' ); }
		public function get_total(): string { return (string) ( $this->d['total'] ?? '0' ); }
		public function get_product_id(): int { return (int) ( $this->d['product_id'] ?? 0 ); }
		public function get_variation_id(): int { return (int) ( $this->d['variation_id'] ?? 0 ); }
	}
}

if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
	class WC_Order_Item_Shipping {
		public function __construct( private array $d ) {}
		public function get_method_id(): string { return (string) ( $this->d['method_id'] ?? '' ); }
		public function get_meta( string $key, bool $single = true ) { return $this->d['meta'][ $key ] ?? ''; }
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public array $meta = [];
		public array $notes = [];
		public function __construct( private array $d ) { $this->meta = $d['meta'] ?? []; }
		public function __call( string $name, array $args ) {
			if ( str_starts_with( $name, 'get_' ) ) {
				$key = substr( $name, 4 );
				return $this->d[ $key ] ?? '';
			}
			throw new \BadMethodCallException( $name );
		}
		public function get_id(): int { return (int) ( $this->d['id'] ?? 0 ); }
		public function get_items(): array { return $this->d['items'] ?? []; }
		public function get_shipping_methods(): array { return $this->d['shipping_methods'] ?? []; }
		public function get_item_count(): int { return array_sum( array_map( fn( $i ) => $i->get_quantity(), $this->get_items() ) ); }
		public function get_meta( string $key, bool $single = true ) { return $this->meta[ $key ] ?? ''; }
		public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
		public function add_order_note( string $note ): void { $this->notes[] = $note; }
		public function save(): void {}
	}
}
