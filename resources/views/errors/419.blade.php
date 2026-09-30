@extends('errors.layout')

@section('code', '419')
@section('title', 'انتهت صلاحية الصفحة')
@section('message', 'مرّ وقت طويل على فتح الصفحة. حدّث الصفحة ثم حاول مرة أخرى.')
@section('actions')
    <a class="btn secondary" href="{{ url()->previous() }}">رجوع</a>
@endsection
