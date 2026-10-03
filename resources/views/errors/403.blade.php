@extends('errors.layout')
@section('code', '403')
@section('title', 'Ruxsat yo‘q')
@section('message', $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Bu sahifani ko‘rish uchun huquqingiz yetarli emas.')
