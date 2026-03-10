<?php
/**
 * Field classes for OxyPods...
 *
 * HOW THIS FILE IS STRUCTURED
 * ---------------------------
 * Each class mirrors the structure of native Breakdance field classes exactly:
 *
 *  - handler() uses get_the_ID() as post ID — same as PostCustomField, AcfImageField, MetaboxGalleryField
 *  - handler() has NO ob_start() wrapper — native classes have none
 *  - handler() uses get_post_meta() directly — no Pods ORM calls that can produce output
 *  - ImageData::fromAttachmentId() is called directly — same as PostImageAttachments, MetaboxGalleryField
 *  - proOnly() returns false — same as PostCustomField
 *
 * The PHP 8 constraint: Field::availableForPostType() has NO type hint on $postType.
 * Adding one in a child class causes a Fatal Error. All overrides must omit the hint.
 *
 * @package OxyPods
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// SHARED TRAIT
// ---------------------------------------------------------------------------

trait PodsOxygen6_FieldBase {

    public array $field = [];

    public function __construct( array $field ) {
        $this->field = $field;
    }

    public function label(): string {
        return (string) ( $this->field['label'] ?? '' );
    }

    public function category(): string {
        return 'Pods';
    }

    public function subcategory(): string {
        return (string) ( $this->field['pod_label'] ?: ( $this->field['pod'] ?? '' ) );
    }

    // No type hint — Fatal Error in PHP 8 if parent has none.
    public function availableForPostType( $postType ): bool {
        return isset( $this->field['pod'] ) && $this->field['pod'] === $postType;
    }

    // Return false like PostCustomField — proOnly = true hides fields in some free-mode checks.
    public function proOnly(): bool {
        return false;
    }
}

// ---------------------------------------------------------------------------
// META HELPERS
// ---------------------------------------------------------------------------

/**
 * Get WP attachment IDs stored by Pods for a file/image field.
 *
 * Pods writes relationship data two ways:
 *   _pods_{field}  → single array-typed meta row (fastest)
 *   {field}        → one meta row per attachment ID
 *
 * We try both. No Pods ORM, no output risk, matches how MetaboxGalleryField
 * reads its data (direct meta access via rwmb_get_value → get_metadata).
 *
 * @param int    $post_id
 * @param string $field_name
 * @return int[]
 */
function oxypods_get_attachment_ids( int $post_id, string $field_name ): array {
    if ( $post_id <= 0 ) {
        return [];
    }

    // Layer 1 – _pods_ meta key written by PodsAPI::save_relationships().
    $raw = get_post_meta( $post_id, '_pods_' . $field_name, true );
    if ( ! empty( $raw ) && is_array( $raw ) ) {
        $ids = array_values( array_filter( array_map( 'intval', $raw ) ) );
        if ( ! empty( $ids ) ) {
            return $ids;
        }
    }

    // Layer 2 – plain meta rows (one per attachment).
    $rows = get_post_meta( $post_id, $field_name, false );
    if ( ! empty( $rows ) && is_array( $rows ) ) {
        $ids = [];
        foreach ( $rows as $row ) {
            $id = is_array( $row )
                ? (int) ( $row['ID'] ?? $row['id'] ?? 0 )
                : (int) $row;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        $ids = array_values( array_unique( $ids ) );
        if ( ! empty( $ids ) ) {
            return $ids;
        }
    }

    return [];
}

// ---------------------------------------------------------------------------
// FIELD CLASSES
// ---------------------------------------------------------------------------

/**
 * String / scalar fields (text, paragraph, wysiwyg, date, etc.)
 *
 * Mirrors PostCustomField::handler() exactly:
 *   get_post_meta( get_the_ID(), $key, true )
 * No ORM, no output buffering.
 */
class PodsOxygen6_StringField extends Breakdance\DynamicData\StringField {
    use PodsOxygen6_FieldBase;

    public function slug(): string {
        return 'pods_field_' . $this->field['pod'] . '_' . $this->field['name'];
    }

    public function handler( $attributes ): Breakdance\DynamicData\StringData {
        $value = get_post_meta( get_the_ID(), $this->field['name'], true );
        if ( ! $value ) {
            return Breakdance\DynamicData\StringData::emptyString();
        }
        return Breakdance\DynamicData\StringData::fromString( (string) $value );
    }
}

/**
 * Single-image file fields.
 *
 * Mirrors PostFeaturedImage / AcfImageField pattern:
 *   ImageData::fromAttachmentId() with attachment ID from meta.
 */
class PodsOxygen6_ImageField extends Breakdance\DynamicData\ImageField {
    use PodsOxygen6_FieldBase;

    public function slug(): string {
        return 'pods_image_' . $this->field['pod'] . '_' . $this->field['name'];
    }

    public function handler( $attributes ): Breakdance\DynamicData\ImageData {
        $ids = oxypods_get_attachment_ids( get_the_ID(), $this->field['name'] );
        if ( empty( $ids ) ) {
            return Breakdance\DynamicData\ImageData::emptyImage();
        }
        return Breakdance\DynamicData\ImageData::fromAttachmentId( $ids[0] );
    }
}

/**
 * Gallery (multi-image) fields.
 *
 * Mirrors MetaboxGalleryField::handler() and PostImageAttachments::handler():
 *   array_map( ImageData::fromAttachmentId, $ids )
 * No ob_start, no ORM.
 */
class PodsOxygen6_GalleryField extends Breakdance\DynamicData\GalleryField {
    use PodsOxygen6_FieldBase;

    public function slug(): string {
        return 'pods_gallery_' . $this->field['pod'] . '_' . $this->field['name'];
    }

    public function handler( $attributes ): Breakdance\DynamicData\GalleryData {
        $ids = oxypods_get_attachment_ids( get_the_ID(), $this->field['name'] );

        if ( empty( $ids ) ) {
            return new Breakdance\DynamicData\GalleryData();
        }

        $gallery         = new Breakdance\DynamicData\GalleryData();
        $gallery->images = array_map(
            static function ( int $id ): Breakdance\DynamicData\ImageData {
                return Breakdance\DynamicData\ImageData::fromAttachmentId( $id );
            },
            $ids
        );

        return $gallery;
    }
}

/**
 * Non-image file fields — exposed as a URL string.
 */
class PodsOxygen6_FileUrlField extends Breakdance\DynamicData\StringField {
    use PodsOxygen6_FieldBase;

    public function slug(): string {
        return 'pods_fileurl_' . $this->field['pod'] . '_' . $this->field['name'];
    }

    public function returnTypes(): array {
        return [ 'string', 'url' ];
    }

    public function handler( $attributes ): Breakdance\DynamicData\StringData {
        $ids = oxypods_get_attachment_ids( get_the_ID(), $this->field['name'] );
        if ( empty( $ids ) ) {
            return Breakdance\DynamicData\StringData::emptyString();
        }
        $url = (string) wp_get_attachment_url( $ids[0] );
        return Breakdance\DynamicData\StringData::fromString( $url );
    }
}
