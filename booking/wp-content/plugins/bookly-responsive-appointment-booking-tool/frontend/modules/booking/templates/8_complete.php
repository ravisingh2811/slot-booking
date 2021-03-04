<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;

echo $progress_tracker;
?>
<div class="bookly-box"><?php echo $info_text ?></div>
<?php if ( get_option( 'bookly_app_show_start_over' ) ) : ?>
<div class="bookly-box bookly-nav-steps">
    <div class="<?php echo get_option( 'bookly_app_align_buttons_left' ) ? 'bookly-left' : 'bookly-right' ?>">
        <button class="bookly-next-step bookly-js-start-over bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
            <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_done_button_start_over' ) ?></span>
        </button>
    </div>
</div>
<?php endif ?>