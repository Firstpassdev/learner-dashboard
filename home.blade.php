@extends('layouts.app')

<?php
use Carbon\Carbon;

/**
 * Extend the timeslot end-time by +30 minutes (used for modal display time)
 * Ex: "09:30 am-11:30 am" => "09:30 am-12:00 pm"
 */
function timeslot($timeSlot){
    if(!$timeSlot || strpos($timeSlot, '-') === false) return $timeSlot;

    list($startTime, $endTime) = explode('-', $timeSlot);
    $endTime = Carbon::parse(trim($endTime));
    $endTime->addMinutes(30);
    $newEndTime = $endTime->format('h:i a');

    return trim($startTime) . '-' . $newEndTime;
}
?>

@section('content')
<style>
    /* SweetAlert buttons */
    button.swal2-confirm.swal2-styled { background:#dd3333 !important; color:#fff !important; }
    button.swal2-cancel.swal2-styled  { background:green !important; }

    /* ===== Learner Dashboard Look & Feel (Instructor Theme) ===== */
    body, .page-wrapper { background:#f5f8fc !important; }

    .page-breadcrumb{
        background: transparent !important;
        padding: 12px 0 !important;
    }

    /* Soft cards like learner dashboard */
    .card{
        border: 1px solid #e8eef6 !important;
        border-radius: 14px !important;
        box-shadow: 0 6px 18px rgba(16, 24, 40, 0.06) !important;
        overflow: hidden;
    }

    .card-title{ font-weight:700 !important; color:#0f172a !important; }
    .card-subtitle, .text-muted{ color:#64748b !important; }

    hr{ border-top:1px solid #edf2f7 !important; }

    /* Buttons match learner style */
    .btn{ border-radius:10px !important; font-weight:600 !important; }
    .btn-success{ background:#16a34a !important; border-color:#16a34a !important; }
    .btn-primary{ background:#2563eb !important; border-color:#2563eb !important; }
    .btn-danger{  background:#ef4444 !important; border-color:#ef4444 !important; }

    /* Pills like learner */
    .status-pill{
        display:inline-flex;
        align-items:center;
        padding:6px 12px;
        border-radius:999px;
        font-weight:700;
        font-size:12px;
        background:#f1f5f9;
        border:1px solid #e2e8f0;
        color:#334155;
        white-space:nowrap;
    }
    .status-pill.success{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
    .status-pill.danger{  background:#fee2e2; border-color:#fecaca; color:#991b1b; }
    .status-pill.warn{    background:#ffedd5; border-color:#fed7aa; color:#9a3412; }

    /* ✅ compact booking rows (status stays on RIGHT) */
    .booking-row{
        display:grid;
        grid-template-columns: 260px 190px 180px 1fr 180px;
        gap:12px;
        align-items:center;
        padding:12px 14px;
        border:1px solid #e8eef6;
        border-radius:12px;
        background:#fff;
    }
    .booking-instructor{display:flex; gap:10px; align-items:center; min-width:0;}
    .booking-avatar{width:26px;height:26px;border-radius:50%;object-fit:cover}
    .booking-name{font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
    .booking-phone{font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; margin-top:2px;}

    .booking-col{
        color:#64748b;
        min-width:0;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .booking-actions{
        justify-self:end;
        text-align:right;
        white-space:nowrap;
    }

    /* ===== Booking History: show more + filters + hide toggle ===== */
    .history-toolbar{
        display:none;
        margin: 8px 0 14px;
        padding: 10px 12px;
        border: 1px solid #e8eef6;
        border-radius: 12px;
        background: #fff;
    }
    .history-filter{
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        align-items:center;
    }
    .filter-pill{
        cursor:pointer;
        user-select:none;
        padding:8px 12px;
        border-radius:999px;
        border:1px solid #e2e8f0;
        background:#f8fafc;
        color:#334155;
        font-weight:700;
        font-size:12px;
    }
    .filter-pill.active{
        background:#2563eb;
        border-color:#2563eb;
        color:#fff;
    }
    .show-more-wrap{
        text-align:center;
        padding: 10px 0 4px;
    }
    .show-more-link{
        font-weight:700;
        color:#2563eb;
        cursor:pointer;
        font-size:12px;
    }
    .history-empty{
        display:none;
        text-align:center;
        color:#64748b;
        font-weight:600;
        padding: 14px 0;
    }

    /* Empty state */
    .empty-state{
        border: 1px dashed #cfe1ff;
        background: #ffffff;
        border-radius: 14px;
        padding: 18px;
        margin-top: 12px;
    }
    .empty-title{
        font-weight: 800;
        color: #0f172a;
        font-size: 16px;
        margin-bottom: 6px;
    }
    .empty-text{
        color:#64748b;
        margin-bottom: 12px;
    }
    .empty-actions{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
    }
    .btn-outline-primary{
        border:1px solid #2563eb !important;
        color:#2563eb !important;
        background: transparent !important;
    }
    .btn-outline-primary:hover{
        background:#2563eb !important;
        color:#fff !important;
    }

    @media (max-width: 768px){
        .booking-row{ grid-template-columns:1fr; gap:8px; }
        .booking-actions{ justify-self:start; text-align:left; }
        .booking-col{ white-space:normal; }
    }
</style>

@php
    // statuses to exclude from "Next/Upcoming"
    $excludedStatuses = ['cancelled','cancelled_payment_wave','no_charge'];

    // Helpers
    $formatAddr = function($raw){
        if(!$raw) return '—';
        $obj = @json_decode($raw);
        if($obj && json_last_error() === JSON_ERROR_NONE){
            $a = trim((@$obj->address ?? ''));
            $city = trim((@$obj->address_detail->city ?? ''));
            $country = trim((@$obj->address_detail->country ?? ''));
            $out = $a;
            if($city) $out .= ($out ? ', ' : '').$city;
            if($country) $out .= ($out ? ', ' : '').$country;
            return $out ?: '—';
        }
        return trim($raw) ?: '—';
    };

    $daysText = function($dateStr){
        if(!$dateStr) return '';
        $expDate   = date_create(date('d-m-Y', strtotime($dateStr)));
        $todayDate = date_create(date('d-m-Y'));
        $diff      = date_diff($todayDate, $expDate);
        return ($diff->format("%R%a") > 0) ? (' in '.$diff->format("%a days")) : ' is today';
    };

    $avatarUrl = function($appt){
        if(empty($appt->avatar)){
            return (($appt->gender ?? 'male') === 'female')
                ? asset('assets/images/users/default-female.png')
                : asset('assets/images/users/default.png');
        }
        return asset('assets/images/users/'.$appt->avatar);
    };

    $buildTitle = function($appt){
        $type = $appt->apptype ?? '';
        $tType = $appt->t_type ?? '';
        $isManual = ($tType === 'manual');

        if($type === 'test'){
            return $isManual ? 'Manual Driving Test' : 'Auto Driving Test';
        }

        $gear = $isManual ? 'Manual' : 'Auto';
        $hrs  = (float)($appt->lesson_hour ?? 1);
        return $gear.' Lesson - '.$hrs.' hour'.($hrs > 1 ? 's' : '');
    };

    $dateStr = function($appt){
        return $appt->schedule_date ? date('D, d M Y', strtotime($appt->schedule_date)) : '—';
    };

    $timeStr = function($appt){
        if(($appt->apptype ?? '') === 'test'){
            $start_date = $appt->start_date ?? null;
            $pickup = $start_date ? date('h:i a', strtotime($start_date) - 3600) : '—';
            $startT = $start_date ? date('h:i a', strtotime($start_date)) : '—';
            return "Pickup {$pickup} • Start {$startT}";
        }
        return $appt->time_slot ?: '—';
    };

    $fullName = function($appt){
        return ucwords(trim(($appt->name ?? '').' '.($appt->lname ?? '')));
    };

    $statusPill = function($status){
        $status = strtolower((string)$status);

        if($status === 'cancelled_payment_wave') return '<span class="status-pill">Payment waived</span>';
        if($status === 'cancelled')             return '<span class="status-pill">Cancelled by Learner</span>';
        if($status === 'no_charge')             return '<span class="status-pill warn">No show (completed)</span>';
        if($status === 'completed')             return '<span class="status-pill success">Payment (Completed)</span>';

        return '<span class="status-pill">'.e(ucfirst($status)).'</span>';
    };

    // ===== Build "upcoming list" and "next upcoming" consistently =====
    $todayYmd = date('Y-m-d');
    $upcomingItems = [];

    if(isset($appointments) && $appointments->isNotEmpty()){
        foreach($appointments as $a){
            $st = strtolower($a->status ?? '');
            if(in_array($st, $excludedStatuses)) continue;
            if(empty($a->schedule_date)) continue;

            // keep future/today
            $sched = date('Y-m-d', strtotime($a->schedule_date));
            if(strtotime($sched) < strtotime($todayYmd)) continue;

            $upcomingItems[] = $a;
        }
    }

    // Sort by schedule_date asc (and if start_date exists, also by that)
    usort($upcomingItems, function($x, $y){
        $dx = strtotime($x->start_date ?? $x->schedule_date ?? '1970-01-01');
        $dy = strtotime($y->start_date ?? $y->schedule_date ?? '1970-01-01');
        return $dx <=> $dy;
    });

    $next = count($upcomingItems) ? $upcomingItems[0] : null;
    $hasNextValid = !is_null($next);
    $hasUpcomingValid = count($upcomingItems) > 0;
    $hasHistoryAny = (isset($BookingHistory) && $BookingHistory->count() > 0);
    $hasAnyLessons = ($hasUpcomingValid || $hasHistoryAny);

@endphp

<div class="page-breadcrumb">
    <div class="row">
        <div class="col-5 align-self-center"></div>
        <div class="col-7 align-self-center">
            <div class="d-flex no-block justify-content-end align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{url('home')}}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    <!-- Welcome back -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="m-r-10">
                            @if(auth()->user()->avatar == '')
                                @if(auth()->user()->gender == 'male')
                                    <img src="{{ url('assets/images/users/default.png') }}" alt="user" class="image_preview rounded-circle" width="31">
                                @else
                                    <img src="{{ url('assets/images/users/default-female.png') }}" alt="user" class="image_preview rounded-circle" width="31">
                                @endif
                            @else
                                <img src="{{ url('assets/images/users/'.auth()->user()->avatar) }}" alt="user" class="image_preview rounded-circle" width="31">
                            @endif
                        </div>
                        <div>
                            <h3 class="m-b-0">Welcome back!</h3>
                            <span class="text-muted">{{ \Carbon\Carbon::now()->format('l jS \of F Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="card-group">
        <div class="card">
            <a href="{{ url('appointments?type=total') }}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h3>{{ $TotalLesson }}</h3>
                            <h6 class="card-subtitle">Total Lessons</h6>
                        </div>
                        <div class="col-12">
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar"
                                     style="width:100%; height:6px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="card">
            <a href="{{ url('appointments?type=completed') }}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h3>{{ $TotalLessonCompleted }}</h3>
                            <h6 class="card-subtitle">Total Completed Lessons</h6>
                        </div>
                        <div class="col-12">
                            <div class="progress">
                                <div class="progress-bar bg-info" role="progressbar"
                                     style="width:100%; height:6px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="card">
            <a href="{{ route('instructor.learners') }}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h3>{{ $TotalLearner }}</h3>
                            <h6 class="card-subtitle">Total Learners</h6>
                        </div>
                        <div class="col-12">
                            <div class="progress">
                                <div class="progress-bar bg-danger" role="progressbar"
                                     style="width:100%; height:6px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">

        <!-- LEFT -->
        <div class="col-sm-12 col-lg-8">

            {{-- Empty state: absolutely no lessons at all --}}
            @if(!$hasAnyLessons)
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-title">No lessons yet — let’s get you booked.</div>
                            <div class="empty-text">
                                Share your profile and update your availability so learners can find and book you.
                            </div>
                            <div class="empty-actions">
                                <a class="btn btn-primary btn-sm" href="{{ url('instructor/profile') }}">Share your profile</a>
                                <a class="btn btn-outline-primary btn-sm" href="{{ url('instructor/profile/edit') }}">Update profile</a>
                                <a class="btn btn-outline-primary btn-sm" href="{{ url('instructor/availability') }}">Set availability</a>
                                <a class="btn btn-outline-primary btn-sm" href="{{ url('instructor/time-slots') }}">Add time slots</a>
                            </div>
                            <div class="empty-text" style="margin-top:12px;margin-bottom:0;">
                                Tip: instructors with a complete bio and the next 7 days available get booked faster.
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Next Lesson (always based on upcoming list) -->
            <div class="card">
                <div class="card-body p-b-0">
                    @if($hasNextValid)
                        @php
                            $days = $daysText($next->schedule_date);
                            $avatar = $avatarUrl($next);
                            $learnerName = $fullName($next);
                            $phone = $next->phone ?? '';
                            $date = $dateStr($next);
                            $time = $timeStr($next);
                            $addr = $formatAddr($next->address ?? '');
                            $title = $buildTitle($next);
                        @endphp

                        <h4 class="card-title" style="color:#f97316 !important;">Your lesson{{ $days }}</h4>
                        <hr>

                        <div class="booking-row">
                            <div class="booking-instructor">
                                <img class="booking-avatar" src="{{ $avatar }}"
                                     onerror="this.src='{{ asset('assets/images/users/default.png') }}'">
                                <div style="min-width:0;">
                                    <div class="booking-name">{{ $learnerName }}</div>
                                    @if($phone)
                                        <a class="booking-phone" href="tel:{{ $phone }}"><i class="fa fa-phone"></i> {{ $phone }}</a>
                                    @endif
                                </div>
                            </div>

                            <div class="booking-col" title="{{ $title }}">{{ $title }}</div>
                            <div class="booking-col">{{ $date }}</div>
                            <div class="booking-col" title="{{ $addr }}">{{ $time }} • {{ $addr }}</div>

                            <div class="booking-actions">
                                @if(($next->status ?? '') == 'confirmed')
                                    <a href="javascript:;"
                                       onclick="ShowTimeSlots(this)"
                                       data-date="{{ date('D, d F, Y', strtotime($next->schedule_date)) }}"
                                       data-time="{{ $next->time_slot }}"
                                       data-name="{{ $learnerName }}"
                                       data-id="{{ $next->id }}"
                                       data-search-id="{{ $next->search_id }}"
                                       data-instructor-id="{{ $next->instructor_id }}"
                                       data-start-date="{{ $next->schedule_date }}"
                                       data-type="{{ $next->apptype }}"
                                       class="btn btn-success btn-sm">Authorise / Waive</a>
                                @else
                                    {!! $statusPill($next->status) !!}
                                @endif
                            </div>
                        </div>
                    @else
                        <h4 class="card-title">No upcoming lessons.</h4>
                        <hr>
                        <p class="text-muted" style="margin-top:-6px;">Cancelled/no-show bookings are shown in Booking History.</p>
                    @endif
                </div>
            </div>

            <!-- Upcoming Lesson list (same data as Next Lesson) -->
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Upcoming Lesson</h4>
                    <br>

                    @if(count($upcomingItems))
                        @foreach($upcomingItems as $i => $appointment)

                            {{-- skip the first one because it is already shown as "Next Lesson" --}}
                            @if($hasNextValid && $appointment->id == $next->id)
                                @continue
                            @endif

                            @php
                                $days = $daysText($appointment->schedule_date);
                                $avatar = $avatarUrl($appointment);
                                $learnerName = $fullName($appointment);
                                $phone = $appointment->phone ?? '';
                                $date = $dateStr($appointment);
                                $time = $timeStr($appointment);
                                $addr = $formatAddr($appointment->address ?? '');
                                $title = $buildTitle($appointment);
                            @endphp

                            <h4 class="card-title">Your lesson{{ $days }}</h4>
                            <hr>

                            <div class="booking-row">
                                <div class="booking-instructor">
                                    <img class="booking-avatar" src="{{ $avatar }}"
                                         onerror="this.src='{{ asset('assets/images/users/default.png') }}'">
                                    <div style="min-width:0;">
                                        <div class="booking-name">{{ $learnerName }}</div>
                                        @if($phone)
                                            <a class="booking-phone" href="tel:{{ $phone }}"><i class="fa fa-phone"></i> {{ $phone }}</a>
                                        @endif
                                    </div>
                                </div>

                                <div class="booking-col" title="{{ $title }}">{{ $title }}</div>
                                <div class="booking-col">{{ $date }}</div>
                                <div class="booking-col" title="{{ $addr }}">{{ $time }} • {{ $addr }}</div>

                                <div class="booking-actions">
                                    @if(($appointment->status ?? '') == 'confirmed')
                                        <a href="javascript:;"
                                           onclick="ShowTimeSlots(this)"
                                           data-date="{{ date('D, d F, Y', strtotime($appointment->schedule_date)) }}"
                                           data-time="{{ timeslot($appointment->time_slot) }}"
                                           data-name="{{ $learnerName }}"
                                           data-id="{{ $appointment->id }}"
                                           data-search-id="{{ $appointment->search_id }}"
                                           data-instructor-id="{{ $appointment->instructor_id }}"
                                           data-start-date="{{ $appointment->schedule_date }}"
                                           data-type="{{ $appointment->apptype }}"
                                           class="btn btn-success btn-sm">Authorise / Waive</a>
                                    @else
                                        {!! $statusPill($appointment->status) !!}
                                    @endif
                                </div>
                            </div>

                        @endforeach
                    @else
                        <p class="text-muted">No upcoming lessons.</p>
                    @endif
                </div>
            </div>

            <!-- Booking History (Collapsed + Show more + Filters + Hide toggle) -->
            @php
                $INITIAL_VISIBLE = 3;
                $historyCount = isset($BookingHistory) ? $BookingHistory->count() : 0;
                $hiddenCount = max(0, $historyCount - $INITIAL_VISIBLE);
            @endphp

            <div class="card">
                <div class="card-body p-b-0">
                    <h4 class="card-title">Booking History</h4>

                    <div id="historyToolbar" class="history-toolbar">
                        <div class="history-filter">
                            <span class="filter-pill active" data-filter="all">All</span>
                            <span class="filter-pill" data-filter="completed">Payment completed</span>
                            <span class="filter-pill" data-filter="cancelled_payment_wave">Payment waived</span>
                            <span class="filter-pill" data-filter="no_charge">No show</span>
                            <span class="filter-pill" data-filter="cancelled">Cancelled</span>
                        </div>
                    </div>

                    <div class="w-100" id="historyList">
                        @if(isset($BookingHistory) && $BookingHistory->isNotEmpty())
                            @foreach($BookingHistory as $index => $appointment)
                                @php
                                    $skey = strtolower((string)($appointment->status ?? 'other'));
                                    if(!in_array($skey, ['completed','cancelled_payment_wave','no_charge','cancelled'])) $skey = 'other';

                                    $avatar = $avatarUrl($appointment);
                                    $learnerName = $fullName($appointment);
                                    $phone = $appointment->phone ?? '';
                                    $date = $dateStr($appointment);
                                    $time = $timeStr($appointment);
                                    $addr = $formatAddr($appointment->address ?? '');
                                    $title = $buildTitle($appointment);
                                @endphp

                                <div class="history-item" data-status="{{ $skey }}" @if($index >= $INITIAL_VISIBLE) style="display:none;" @endif>
                                    <div class="booking-row" style="margin-top:12px;">
                                        <div class="booking-instructor">
                                            <img class="booking-avatar" src="{{ $avatar }}"
                                                 onerror="this.src='{{ asset('assets/images/users/default.png') }}'">
                                            <div style="min-width:0;">
                                                <div class="booking-name">{{ $learnerName }}</div>
                                                @if($phone)
                                                    <a class="booking-phone" href="tel:{{ $phone }}"><i class="fa fa-phone"></i> {{ $phone }}</a>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="booking-col" title="{{ $title }}">{{ $title }}</div>
                                        <div class="booking-col">{{ $date }}</div>
                                        <div class="booking-col" title="{{ $addr }}">{{ $time }} • {{ $addr }}</div>

                                        <div class="booking-actions">
                                            {!! $statusPill($appointment->status) !!}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted">No booking history yet.</p>
                        @endif
                    </div>

                    <div id="historyEmpty" class="history-empty">No bookings match this filter.</div>

                    @if($hiddenCount > 0)
                        <div class="show-more-wrap">
                            <span id="toggleHistory" class="show-more-link">Show more</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <!-- RIGHT -->
        <div class="col-sm-12 col-lg-4">

            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Total Amount (Learners Paid)</h4>
                    <div class="d-flex align-items-center flex-row m-t-30">
                        <h2><strong>${{ (isset($TotalAmount)) ? $TotalAmount : '0' }}</strong></h2>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Withdrawable Amount</h4>
                    <div class="d-flex align-items-center flex-row m-t-30">
                        <h2><strong>${{ (isset($GetCompletedAppointmentsAmount)) ? $GetCompletedAppointmentsAmount : '0' }}</strong></h2>
                    </div>

                    @if(isset($GetCompletedAppointmentsAmount) && $GetCompletedAppointmentsAmount > 0)
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#WithDrawAmountModal">
                            Processed To Withdraw
                        </button>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Payment Authorisation -->
    <div class="modal fade" id="TimeSlotModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form id="book_time" method="post">
                <input type="hidden" name="schedule_date">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Payment Authorisation</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-12">
                                    <h3>
                                        Has your <span id="leasongOrTestPackage"></span> with
                                        <span style="font-weight:bold" id="learnerName">Cordner</span>
                                        scheduled for <span id="learnerDate" style="font-weight:bold">31 Aug 2020,13:00 PM</span>
                                        been completed ?
                                    </h3>

                                    <a href="javascript:;" onclick="Yes(this)" class="btn btn-success"
                                       style="margin-bottom:20px;margin-right:10px;border-radius:8px;width:100%;padding:6px;font-size:17px;">
                                        <i class="fa fa-check"></i> Yes
                                    </a>

                                    <a href="javascript:;" onclick="No(this)" class="btn btn-danger"
                                       style="margin-bottom:20px;margin-right:10px;border-radius:8px;width:100%;padding:6px;font-size:17px;">
                                        <i class="fa fa-times"></i> No
                                    </a>

                                    <input type="hidden" id="selectedPaymentOptions" value="" name="selected_options">
                                    <input type="hidden" id="canceledStatus" value="cancelled_payment_wave" name="selected_status">

                                    <input type="hidden" class="form-control" name="search_id" id="search_id">
                                    <input type="hidden" class="form-control" name="id" id="appt_id">
                                    <input type="hidden" class="form-control" name="instructor_id" id="instructor_id">

                                    <div id="checkingOptionsForNo" style="padding:10px;border:1px solid green;border-radius:10px;display:none">
                                        <p style="margin-bottom:0;font-size:18px;font-weight:500;">Please select from two options:</p>
                                        <p style="margin-bottom:0;font-size:18px;font-weight:500;">1. Enforce the 8 hours cancellation policy and collect full payment for the booking.</p>
                                        <p style="margin-bottom:0;font-size:18px;font-weight:500;">2. Waive the full booking payment.</p>
                                        <br/>
                                        <input checked type="radio" name="cancel_options" value="cancelled_payment_wave">
                                        <span style="font-size:16px;font-weight:500;">Waive the booking payment</span><br/>
                                        <input type="radio" name="cancel_options" value="no_charge">
                                        <span style="font-size:16px;font-weight:500;">Charge the learner for the booking payment</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="confirmandGetPaid" style="display:none">Confirm and Get Paid</button>
                        <button type="submit" class="btn btn-danger" id="confirmandGetCancel" style="display:none">Confirm</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div class="modal fade" id="WithDrawAmountModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="withdraw_form">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">WithDraw Payment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="instructor_id" value="{{ Auth::user()->id  }}">
                        <div class="form-group">
                            <label>Enter Amount</label>
                            <div class="input-group">
                                <input type="text" name="amount" class="form-control" placeholder="Enter Withdraw Amount">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">WithDraw</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/b-html5-1.5.4/b-print-1.5.4/fh-3.1.4/datatables.min.js"></script>
<script src="{{ asset('assets/js/calendar/packages/core/main.js') }}"></script>
<script src="{{ asset('assets/js/calendar/packages/interaction/main.js') }}"></script>
<script src="{{ asset('assets/js/calendar/packages/daygrid/main.js') }}"></script>
<script src="{{ asset('assets/js/calendar/packages/moment/moment.min.js') }}"></script>
<script src="{{ asset('assets/js/calendar/packages/timegrid/main.js')}}"></script>
<script src="{{ asset('assets/js/pages/dashboards/dashboard3.js')}}"></script>

<script>
    function ShowTimeSlots(e){
        var type = $(e).attr('data-type');
        var date = $(e).attr('data-date');
        var time = $(e).attr('data-time');
        var name = $(e).attr('data-name');
        var instructor_id = $(e).attr('data-instructor-id');
        var search_id = $(e).attr('data-search-id');
        var id = $(e).attr('data-id');

        if (type=='lesson'){
            $('span#leasongOrTestPackage').html('lesson');
        }else if (type=='test'){
            $('span#leasongOrTestPackage').html('auto driving test');
        }

        $('span#learnerName').html(name);
        $('span#learnerDate').html(date+' '+time);

        $('#instructor_id').val(instructor_id);
        $('#appt_id').val(id);
        $('#search_id').val(search_id);

        $('#TimeSlotModal').modal('show');
    }

    function Yes(){
        $('button#confirmandGetPaid').show();
        $('div#checkingOptionsForNo').hide();
        $('button#confirmandGetCancel').hide();
        $('input#selectedPaymentOptions').val('yes');
    }

    function No(){
        $('button#confirmandGetPaid').hide();
        $('div#checkingOptionsForNo').show();
        $('button#confirmandGetCancel').show();
        $('input#selectedPaymentOptions').val('no');
    }

    $(document).ready(function() {
        $("input[name='cancel_options']").click(function(){
            var clickedValue = $("input[name='cancel_options']:checked").val();
            $('input#canceledStatus').val(clickedValue);
        });

        $('#withdraw_form').submit(function (){
            $('#loading').show();
            var data = new FormData(this);

            $.ajax({
                url: "{{ route('withdraw-amount') }}",
                data: data,
                contentType: false,
                processData: false,
                type: 'POST',
                success: function (res) {
                    if(res.success == true){
                        swal('Success', res.message, 'success')
                        .then(function() { location.reload(); });
                    }else if(res.success == false){
                        swal('Warning!', res.message, 'error');
                    }
                    $('#loading').hide();
                },
                error: function () {
                    $('#loading').hide();
                }
            });

            return false;
        });
    });

    $('#book_time').submit(function (event){
        event.preventDefault();

        var checkedValue = $('input[name="cancel_options"]:checked').val();
        let message='I declare that the learner is at fault and did not attend the scheduled booking.';
        if (checkedValue=='cancelled_payment_wave'){
            message='You want to wave the payment?';
        }

        Swal.fire({
            title: 'Are you sure?',
            text: message,
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes'
        }).then((result) => {
            if (result.value) {
                $("#loading").show();
                var paymentOptions = $('input#selectedPaymentOptions').val();
                var InstructorID = $('input#instructor_id').val();
                var AppointmentID = $('input#appt_id').val();

                if (InstructorID && AppointmentID){
                    let status="";
                    if (paymentOptions=='no'){
                        status=$('input#canceledStatus').val();
                    }else if (paymentOptions=='yes'){
                        status='completed';
                    }

                    if (status){
                        $.post('{{ route("Change-appointment-status")}}',
                            {AppointmentID:AppointmentID, InstructorID:InstructorID, status:status},
                            function(res){
                                $("#loading").hide();
                                if(res.success==true){
                                    if (checkedValue=='cancelled_payment_wave'){
                                        swal("Confirmed!",'Confirmed payment waived.', "success");
                                    }else{
                                        swal("Confirmed!",'Successfull Payment', "success");
                                    }
                                    setTimeout(function(){ location.reload(); }, 2000);
                                }else if(res.success==false){
                                    swal("Error!",res.message, "error");
                                }
                            }
                        );
                    }
                }
                return false;
            }
        });
    });

    // Booking history: Toggle Show more <-> Hide + Filters (like learner)
    (function(){
        const initialVisible = {{ $INITIAL_VISIBLE ?? 3 }};
        const historyList = document.getElementById('historyList');
        const toolbar = document.getElementById('historyToolbar');
        const toggleBtn = document.getElementById('toggleHistory');
        const emptyMsg = document.getElementById('historyEmpty');

        if(!historyList) return;

        function getItems(){ return Array.from(historyList.querySelectorAll('.history-item')); }

        function applyFilter(key){
            const items = getItems();
            let any = false;
            const expanded = historyList.getAttribute('data-expanded') === '1';

            items.forEach((el, idx) => {
                const status = (el.getAttribute('data-status') || 'other');
                const match = (key === 'all') ? true : (status === key);

                if(!expanded && idx >= initialVisible){
                    el.style.display = 'none';
                } else {
                    el.style.display = match ? '' : 'none';
                }

                if(el.style.display !== 'none') any = true;
            });

            if(emptyMsg) emptyMsg.style.display = any ? 'none' : 'block';
        }

        function setExpanded(expanded){
            historyList.setAttribute('data-expanded', expanded ? '1' : '0');

            if(toolbar) toolbar.style.display = expanded ? 'block' : 'none';
            if(toggleBtn) toggleBtn.textContent = expanded ? 'Hide' : 'Show more';

            if(!expanded && toolbar){
                toolbar.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
                const all = toolbar.querySelector('.filter-pill[data-filter="all"]');
                if(all) all.classList.add('active');
            }

            const active = toolbar ? toolbar.querySelector('.filter-pill.active') : null;
            applyFilter(active ? active.getAttribute('data-filter') : 'all');
        }

        if(toggleBtn){
            toggleBtn.addEventListener('click', function(){
                const expanded = historyList.getAttribute('data-expanded') === '1';
                setExpanded(!expanded);
            });
        }

        if(toolbar){
            toolbar.addEventListener('click', function(e){
                const pill = e.target.closest('.filter-pill');
                if(!pill) return;

                toolbar.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');

                applyFilter(pill.getAttribute('data-filter') || 'all');
            });
        }

        setExpanded(false);
    })();
</script>
@endsection
