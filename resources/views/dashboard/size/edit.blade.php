@extends('layouts.dashboard.layout')
@section('content')
<div class="app-wrapper">
    <!--begin::App Main-->
    <main class="app-main">
        <!--begin::App Content Header-->
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0">Edit Size</h3></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Size</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!--end::App Content Header-->

        <!--begin::App Content-->
        <div class="app-content">
            <div class="container-fluid">
                <div class="row g-4 d-flex justify-content-center">
                    <div class="col-md-6 mt-md-5">
                        <div class="card card-primary card-outline mb-4">
                            <div class="card-header">
                                <div class="card-title">Edit Size</div>
                            </div>
                            <!--begin::Form-->
                            <form action="{{ route('admin.update.size', $size->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="sizeInput" class="form-label">Size</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="sizeInput"
                                            name="size"
                                            value="{{ old('size', $size->size) }}"
                                        />
                                        @error('size')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                            <!--end::Form-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::App Content-->
    </main>
    <!--end::App Main-->
</div>
@endsection
