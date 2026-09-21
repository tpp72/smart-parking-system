@extends('errors.layout')

@section('code', '419')
@section('title', 'หน้านี้หมดอายุแล้ว')
@section('message', 'คุณเปิดหน้านี้ทิ้งไว้นานเกินไป เพื่อความปลอดภัยระบบจึงยกเลิกการส่งข้อมูล กรุณาเข้าสู่ระบบใหม่แล้วลองอีกครั้ง')

@section('action')
    <x-ui.button href="{{ route('login') }}">เข้าสู่ระบบอีกครั้ง</x-ui.button>
@endsection
