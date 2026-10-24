@extends('errors.layout')

@section('title', __('Sesi sudah berakhir'))
@section('code', __('KESALAHAN 419'))
@section('heading', __('Sesi sudah berakhir'))
@section('message', __('Halaman ini terlalu lama terbuka sehingga sesi keamanannya habis. Muat ulang halaman sebelumnya (tarik layar ke bawah atau tekan F5), lalu ulangi langkah Anda.'))
@section('action')
    <a href="{{ url()->previous('/') }}">{{ __('Kembali ke halaman sebelumnya') }}</a>
@endsection
