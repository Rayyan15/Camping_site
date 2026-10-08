@extends('errors.layout')

@section('title', 'Sesi sudah berakhir')
@section('code', 'KESALAHAN 419')
@section('heading', 'Sesi sudah berakhir')
@section('message', 'Halaman ini terlalu lama terbuka sehingga sesi keamanannya habis. Muat ulang halaman sebelumnya (tarik layar ke bawah atau tekan F5), lalu ulangi langkah Anda.')
@section('action')
    <a href="{{ url()->previous('/') }}">Kembali ke halaman sebelumnya</a>
@endsection
