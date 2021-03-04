<?php
namespace Bookly\Backend\Modules\Calendar;

use Bookly\Lib;
use Bookly\Lib\Config;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Staff;
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Utils\DateTime;
use Bookly\Lib\Utils\Price;

/**
 * Class Page
 * @package Bookly\Backend\Modules\Calendar
 */
class Page extends Lib\Base\Ajax
{
    /**
     * Render page.
     */
    public static function render()
    {
        self::enqueueStyles( array(
            'module'  => array( 'css/event-calendar.min.css'),
            'backend' => array( 'bootstrap/css/bootstrap.min.css' ),
        ) );

        if ( Config::proActive() ) {
            if ( Common::isCurrentUserSupervisor() ) {
                $staff_members = Staff::query()
                    ->whereNot( 'visibility', 'archive' )
                    ->sortBy( 'position' )
                    ->find()
                ;
                $staff_dropdown_data = Lib\Proxy\Pro::getStaffDataForDropDown();
            } else {
                $staff_members = Staff::query()
                    ->where( 'wp_user_id', get_current_user_id() )
                    ->whereNot( 'visibility', 'archive' )
                    ->find()
                ;
                $staff_dropdown_data = array(
                    0 => array(
                        'name'  => '',
                        'items' => empty ( $staff_members ) ? array() : array( $staff_members[0]->getFields() )
                    )
                );
            }
        } else {
            $staff = Staff::query()->findOne();
            $staff_members = $staff ? array( $staff ) : array();
            $staff_dropdown_data = array(
                0 => array(
                    'name'  => '',
                    'items' => empty ( $staff_members ) ? array() : array( $staff_members[0]->getFields() )
                )
            );
        }

        self::enqueueScripts( array(
            'backend' => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/alert.js' => array( 'jquery' ),
                'js/dropdown.js' => array( 'jquery' ),
            ),
            'module' => $staff_members
                ? array(
                    'js/event-calendar.min.js',
                    'js/calendar-common.js' => array( 'bookly-event-calendar.min.js' ),
                    'js/calendar.js'        => array( 'bookly-calendar-common.js', 'bookly-dropdown.js' ),
                )
                : array(),
        ) );

        $slot_length_minutes = get_option( 'bookly_gen_time_slot_length', '15' );
        $slot = new \DateInterval( 'PT' . $slot_length_minutes . 'M' );

        $hidden_days = array();
        $min_time = '00:00:00';
        $max_time = '24:00:00';
        $scroll_time = '08:00:00';
        // Find min and max business hours
        $min = $max = null;
        foreach ( Config::getBusinessHours() as $day => $bh ) {
            if ( $bh['start'] === null ) {
                if ( Config::showOnlyBusinessDaysInCalendar() ) {
                    $hidden_days[] = $day;
                }
                continue;
            }
            if ( $min === null || $bh['start'] < $min ) {
                $min = $bh['start'];
            }
            if ( $max === null || $bh['end'] > $max ) {
                $max = $bh['end'];
            }
        }
        if ( $min !== null ) {
            $scroll_time = $min;
            if ( Config::showOnlyBusinessHoursInCalendar() ) {
                $min_time = $min;
                $max_time = $max;
            } else if ( $max > '24:00:00' ) {
                $min_time = DateTime::buildTimeString( DateTime::timeToSeconds( $max ) - DAY_IN_SECONDS );
                $max_time = $max;
            }
        }

        wp_localize_script( 'bookly-calendar.js', 'BooklyL10n', array(
            'csrf_token'      => Common::getCsrfToken(),
            'hiddenDays'      => $hidden_days,
            'slotDuration'    => $slot->format( '%H:%I:%S' ),
            'slotMinTime'     => $min_time,
            'slotMaxTime'     => $max_time,
            'scrollTime'      => $scroll_time,
            'locale'          => Config::getShortLocale(),
            'mjsTimeFormat'   => DateTime::convertFormat( 'time', DateTime::FORMAT_MOMENT_JS ),
            'datePicker'      => DateTime::datePickerOptions(),
            'dateRange'       => DateTime::dateRangeOptions(),
            'today'           => __( 'Today', 'bookly' ),
            'week'            => __( 'Week',  'bookly' ),
            'day'             => __( 'Day',   'bookly' ),
            'month'           => __( 'Month', 'bookly' ),
            'list'            => __( 'List', 'bookly' ),
            'noEvents'        => __( 'No appointments for selected period.', 'bookly' ),
            'delete'          => __( 'Delete',  'bookly' ),
            'are_you_sure'    => __( 'Are you sure?',     'bookly' ),
            'hideStaffWithNoEvents' => Config::showOnlyStaffWithAppointmentsInCalendarDayView(),
            'recurring_appointments' => array(
                'active' => (int) Config::recurringAppointmentsActive(),
                'title'  => __( 'Recurring appointments', 'bookly' ),
            ),
            'waiting_list'    => array(
                'active' => (int) Config::waitingListActive(),
                'title'  => __( 'On waiting list', 'bookly' ),
            ),
            'packages'    => array(
                'active' => (int) Config::packagesActive(),
                'title'  => __( 'Package', 'bookly' ),
            ),
        ) );

        $refresh_rate = get_user_meta( get_current_user_id(), 'bookly_calendar_refresh_rate', true );
        $services_dropdown_data = Common::getServiceDataForDropDown();

        self::renderTemplate( 'calendar', compact( 'staff_members', 'staff_dropdown_data', 'services_dropdown_data', 'refresh_rate' ) );
    }

    /**
     * Build appointments for Event Calendar.
     *
     * @param Lib\Query $query
     * @param int $staff_id
     * @param string $display_tz
     * @return mixed
     */
    public static function buildAppointmentsForCalendar( Lib\Query $query, $staff_id, $display_tz )
    {
        $one_participant   = '<div>' . str_replace( "\n", '</div><div>', get_option( 'bookly_cal_one_participant' ) ) . '</div>';
        $many_participants = '<div>' . str_replace( "\n", '</div><div>', get_option( 'bookly_cal_many_participants' ) ) . '</div>';
        $postfix_any       = sprintf( ' (%s)', get_option( 'bookly_l10n_option_employee' ) );
        $participants      = null;
        $default_codes     = array(
            'amount_due'        => '',
            'amount_paid'       => '',
            'appointment_date'  => '',
            'appointment_time'  => '',
            'booking_number'    => '',
            'category_name'     => '',
            'client_address'    => '',
            'client_email'      => '',
            'client_name'       => '',
            'client_first_name' => '',
            'client_last_name'  => '',
            'client_phone'      => '',
            'company_address'   => get_option( 'bookly_co_address' ),
            'company_name'      => get_option( 'bookly_co_name' ),
            'company_phone'     => get_option( 'bookly_co_phone' ),
            'company_website'   => get_option( 'bookly_co_website' ),
            'custom_fields'     => '',
            'extras'            => '',
            'extras_total_price'=> 0,
            'internal_note'     => '',
            'location_name'     => '',
            'location_info'     => '',
            'number_of_persons' => '',
            'on_waiting_list'   => '',
            'payment_status'    => '',
            'payment_type'      => '',
            'service_capacity'  => '',
            'service_duration'  => '',
            'service_info'      => '',
            'service_name'      => '',
            'service_price'     => '',
            'signed_up'         => '',
            'staff_email'       => '',
            'staff_info'        => '',
            'staff_name'        => '',
            'staff_phone'       => '',
            'status'            => '',
            'total_price'       => '',
        );
        $query
            ->select( 'a.id, ca.series_id, a.staff_any, a.location_id, a.internal_note, a.start_date, DATE_ADD(a.end_date, INTERVAL IF(ca.extras_consider_duration, a.extras_duration, 0) SECOND) AS end_date,
                COALESCE(s.title,a.custom_service_name) AS service_name, COALESCE(s.color,"silver") AS service_color, s.info AS service_info,
                COALESCE(ss.price,a.custom_service_price) AS service_price,
                st.full_name AS staff_name, st.email AS staff_email, st.info AS staff_info, st.phone AS staff_phone,
                (SELECT SUM(ca.number_of_persons) FROM ' . CustomerAppointment::getTableName() . ' ca WHERE ca.appointment_id = a.id) AS total_number_of_persons,
                s.duration,
                s.start_time_info,
                s.end_time_info,
                ca.number_of_persons,
                ca.units,
                ca.custom_fields,
                ca.status AS status,
                ca.extras,
                ca.extras_multiply_nop,
                ca.package_id,
                ct.name AS category_name,
                c.full_name AS client_name, c.first_name AS client_first_name, c.last_name AS client_last_name, c.phone AS client_phone, c.email AS client_email, c.id AS customer_id,
                p.total, p.type AS payment_gateway, p.status AS payment_status, p.paid,
                (SELECT SUM(ca.number_of_persons) FROM ' . CustomerAppointment::getTableName() . ' ca WHERE ca.appointment_id = a.id AND ca.status = "waitlisted") AS on_waiting_list' )
            ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
            ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
            ->leftJoin( 'Service', 's', 's.id = a.service_id' )
            ->leftJoin( 'Category', 'ct', 'ct.id = s.category_id' )
            ->leftJoin( 'Staff', 'st', 'st.id = a.staff_id' )
            ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = a.staff_id AND ss.service_id = a.service_id' );

        if ( Config::groupBookingActive() ) {
            $query->addSelect( 'COALESCE(ss.capacity_max,9999) AS service_capacity' );
        } else {
            $query->addSelect( '1 AS service_capacity' );
        }

        if ( Config::proActive() ) {
            $query->addSelect( 'c.country, c.state, c.postcode, c.city, c.street, c.street_number, c.additional_address' );
        }

        // Fetch appointments,
        // and shift the dates to appropriate time zone if needed
        $appointments = array();
        $wp_tz = Config::getWPTimeZone();
        $convert_tz = $display_tz !== $wp_tz;

        foreach ( $query->fetchArray() as $appointment ) {
            if ( ! isset ( $appointments[ $appointment['id'] ] ) ) {
                if ( $convert_tz ) {
                    $appointment['start_date'] = DateTime::convertTimeZone( $appointment['start_date'], $wp_tz, $display_tz );
                    $appointment['end_date']   = DateTime::convertTimeZone( $appointment['end_date'], $wp_tz, $display_tz );
                }
                $appointments[ $appointment['id'] ] = $appointment;
            }
            $appointments[ $appointment['id'] ]['customers'][] = array(
                'client_name' => $appointment['client_name'],
                'client_first_name' => $appointment['client_first_name'],
                'client_last_name' => $appointment['client_last_name'],
                'client_phone' => $appointment['client_phone'],
                'client_email' => $appointment['client_email'],
                'payment_status' => Lib\Entities\Payment::statusToString( $appointment['payment_status'] ),
                'payment_type' => Lib\Entities\Payment::typeToString( $appointment['payment_gateway'] ),
                'number_of_persons' => $appointment['number_of_persons'],
                'status' => $appointment['status'],
            );
        }

        $status_codes = array(
            CustomerAppointment::STATUS_APPROVED  => 'success',
            CustomerAppointment::STATUS_CANCELLED => 'danger',
            CustomerAppointment::STATUS_REJECTED  => 'danger',
        );
        $cancelled_statuses = array(
            CustomerAppointment::STATUS_CANCELLED,
            CustomerAppointment::STATUS_REJECTED,
        );
        $pending_statuses = array(
            CustomerAppointment::STATUS_CANCELLED,
            CustomerAppointment::STATUS_REJECTED,
            CustomerAppointment::STATUS_PENDING,
        );

        foreach ( $appointments as $key => $appointment ) {
            $codes = $default_codes;
            $codes['appointment_date'] = DateTime::formatDate( $appointment['start_date'] );
            $codes['appointment_time'] = $appointment['duration'] >= DAY_IN_SECONDS && $appointment['start_time_info'] ? $appointment['start_time_info'] : Lib\Utils\DateTime::formatTime( $appointment['start_date'] );
            $codes['booking_number']   = $appointment['id'];
            $codes['internal_note']    = esc_html( $appointment['internal_note'] );
            $codes['on_waiting_list']  = $appointment['on_waiting_list'];
            $codes['service_name']     = $appointment['service_name'] ? esc_html( $appointment['service_name'] ) : __( 'Untitled', 'bookly' );
            $codes['service_price']    = Price::format( $appointment['service_price'] * $appointment['units'] );
            $codes['service_duration'] = DateTime::secondsToInterval( $appointment['duration'] * $appointment['units'] );
            $codes['signed_up']        = $appointment['total_number_of_persons'];
            foreach ( array( 'staff_name', 'staff_phone', 'staff_info', 'staff_email', 'service_info', 'service_capacity', 'category_name' ) as $field ) {
                $codes[ $field ] = esc_html( $appointment[ $field ] );
            }
            if ( $appointment['staff_any'] ) {
                $codes['staff_name'] .= $postfix_any;
            }

            // Customers for popover.
            $popover_customers = '';
            $overall_status = isset( $appointment['customers'][0] ) ? $appointment['customers'][0]['status'] : '';

            $codes['participants'] = array();

            foreach ( $appointment['customers'] as $customer ) {
                $status_color = 'secondary';
                if ( isset( $status_codes[ $customer['status'] ] ) ) {
                    $status_color = $status_codes[ $customer['status'] ];
                }
                if ( $customer['status'] != $overall_status && ( ! in_array( $customer['status'], $cancelled_statuses ) || ! in_array( $overall_status, $cancelled_statuses ) ) ) {
                    if ( in_array( $customer['status'], $pending_statuses ) && in_array( $overall_status, $pending_statuses ) ) {
                        $overall_status = CustomerAppointment::STATUS_PENDING;
                    } else {
                        $overall_status = '';
                    }
                }
                if ( $customer['number_of_persons'] > 1 ) {
                    $number_of_persons = '<span class="badge badge-info mr-1"><i class="far fa-fw fa-user"></i>×' . $customer['number_of_persons'] . '</span>';
                } else {
                    $number_of_persons = '';
                }
                $popover_customers .= '<div class="d-flex"><div class="text-muted flex-fill">' . $customer['client_name'] . '</div><div class="text-nowrap">' . $number_of_persons . '<span class="badge badge-' . $status_color . '">' . CustomerAppointment::statusToString( $customer['status'] ) . '</span></div></div>';
                $codes['participants'][] = $customer;
            }

            // Display customer information only if there is 1 customer. Don't confuse with number_of_persons.
            if ( $appointment['number_of_persons'] == $appointment['total_number_of_persons'] ) {
                $participants = 'one';
                $template     = $one_participant;
                foreach ( array( 'client_name', 'client_first_name', 'client_last_name', 'client_phone', 'client_email', 'number_of_persons' ) as $data_entry ) {
                    if ( $appointment[ $data_entry ] ) {
                        $codes[ $data_entry ] = esc_html( $appointment[ $data_entry ] );
                    }
                }

                // Payment.
                if ( $appointment['total'] ) {
                    $codes['total_price']    = Price::format( $appointment['total'] );
                    $codes['amount_paid']    = Price::format( $appointment['paid'] );
                    $codes['amount_due']     = Price::format( $appointment['total'] - $appointment['paid'] );
                    $codes['total_price']    = Price::format( $appointment['total'] );
                    $codes['payment_type']   = Lib\Entities\Payment::typeToString( $appointment['payment_gateway'] );
                    $codes['payment_status'] = Lib\Entities\Payment::statusToString( $appointment['payment_status'] );
                }
                // Status.
                $codes['status'] = CustomerAppointment::statusToString( $appointment['status'] );

                $tooltip = '<i class="fas fa-fw fa-circle mr-1" style="color:%s"></i><span>{service_name}</span>' . $popover_customers . '<span class="d-block text-muted">{appointment_time} - %s</span>';

            } else {
                $participants = 'many';
                $template     = $many_participants;
                $tooltip = '<i class="fas fa-fw fa-circle mr-1" style="color:%s"></i><span>{service_name}</span>' . $popover_customers . '<span class="d-block text-muted">{appointment_time} - %s</span>';
            }

            $tooltip = sprintf( $tooltip,
                $appointment['service_color'],
                ( $appointment['duration'] * $appointment['units'] >= DAY_IN_SECONDS && $appointment['start_time_info'] ? $appointment['end_time_info'] : DateTime::formatTime( $appointment['end_date'] ) )
            );

            $codes = Proxy\Shared::prepareAppointmentCodesData( $codes, $appointment, $participants );

            $appointments[ $key ] = array(
                'id'            => $appointment['id'],
                'start'         => $appointment['start_date'],
                'end'           => $appointment['end_date'],
                'title'         => ' ',
                'color'         => $appointment['service_color'],
                'resourceId'    => $staff_id,
                'extendedProps' => array(
                    'tooltip'        => Lib\Utils\Codes::replace( $tooltip, $codes, false ),
                    'desc'           => Lib\Utils\Codes::replace( $template, $codes, false ),
                    'staffId'        => $staff_id,
                    'series_id'      => (int) $appointment['series_id'],
                    'package_id'     => (int) $appointment['package_id'],
                    'waitlisted'     => (int) $appointment['on_waiting_list'],
                    'staff_any'      => (int) $appointment['staff_any'],
                    'overall_status' => $overall_status,
                ),
            );
            if ( $appointment['duration'] * $appointment['units'] >= DAY_IN_SECONDS && $appointment['start_time_info'] ) {
                $appointments[ $key ]['extendedProps']['header_text'] = sprintf( '%s - %s', $appointment['start_time_info'], $appointment['end_time_info'] );
            }
        }

        return array_values( $appointments );
    }
}
