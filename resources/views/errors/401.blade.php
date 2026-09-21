@extends('errors.layout')

@section('code', '401')
@section('title', 'ต้องเข้าสู่ระบบก่อน')
@section('message', 'กรุณาเข้าสู่ระบบเพื่อใช้งานหน้านี้')

@section('action')
    <x-ui.button href="{{ route('login') }}">เข้าสู่ระบบ</x-ui.button>
@endsection
