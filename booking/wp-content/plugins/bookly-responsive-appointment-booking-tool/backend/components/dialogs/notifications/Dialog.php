<?php
namespace Bookly\Backend\Components\Dialogs\Notifications;

use Bookly\Lib;
use Bookly\Backend\Components\Dialogs\Sms\Dialog as SmsDialog;
use Bookly\Backend\Modules\Notifications\Lib\Codes;
use Bookly\Lib\Entities\Notification;

/**
 * Class Dialog
 * @package Bookly\Backend\Components\Dialogs\Notifications
 */
class Dialog extends SmsDialog
{
    /**
     * Render payment details dialog.
     */
    public static function render()
    {
        self::enqueueStyles( array(
            'frontend' => array( 'css/ladda.min.css', ),
            'backend' => array( 'css/fontawesome-all.min.css', ),
        ) );

        self::enqueueScripts( array(
            'frontend' => array(
                'js/spin.min.js' => array( 'jquery', ),
                'js/ladda.min.js' => array( 'jquery', ),
            ),
            'backend' => array( 'js/select2.min.js' => array( 'jquery' ), ),
            'bookly' => array( 'backend/components/dialogs/sms/resources/js/notification-dialog.js' => array( 'jquery' ), ),
        ) );

        $codes = new Codes( 'email' );
        $codes_list = array();
        foreach ( Notification::getTypes() as $notification_type ) {
            $codes_list[ $notification_type ] = $codes->getCodes( $notification_type );
        }

        wp_localize_script( 'bookly-notification-dialog.js', 'BooklyNotificationDialogL10n', array(
            'csrfToken' => Lib\Utils\Common::getCsrfToken(),
            'recurringActive' => (int) Lib\Config::recurringAppointmentsActive(),
            'defaultNotification' => self::getDefaultNotification(),
            'codes' => $codes_list,
            'title' => array(
                'container' => __( 'Email', 'bookly' ),
                'new' => __( 'New email notification', 'bookly' ),
                'edit' => __( 'Edit email notification', 'bookly' ),
                'create' => __( 'Create notification', 'bookly' ),
                'save' => __( 'Save notification', 'bookly' ),
            ),
        ) );

        SmsDialog::renderTemplate( 'dialog', array( 'self' => __CLASS__, 'gateway' => 'email' ) );
    }
}