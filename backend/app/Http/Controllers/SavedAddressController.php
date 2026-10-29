<?php

namespace App\Http\Controllers;

use App\Data\SavedAddressData;
use App\Dtos\SaveAddressDto;
use App\Http\Requests\StoreSavedAddressRequest;
use App\Http\Requests\UpdateSavedAddressRequest;
use App\Services\SavedAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

// The authenticated User's address book; another User's address is a 404.
class SavedAddressController extends Controller
{
    public function __construct(
        private readonly SavedAddressService $savedAddressService,
    ) {}

    public function index(Request $request): DataCollection
    {
        $addresses = $this->savedAddressService->getAddresses($request->user());

        return SavedAddressData::collect($addresses, DataCollection::class)->wrap('data');
    }

    public function store(StoreSavedAddressRequest $request): JsonResponse
    {
        $address = $this->savedAddressService->createAddress($request->user(), SaveAddressDto::fromRequest($request));

        return response()->json(['data' => SavedAddressData::from($address)], 201);
    }

    public function update(UpdateSavedAddressRequest $request, int $address): SavedAddressData
    {
        $updated = $this->savedAddressService->updateAddress($request->user(), $address, SaveAddressDto::fromRequest($request));

        return SavedAddressData::from($updated)->wrap('data');
    }

    public function destroy(int $address, Request $request): JsonResponse
    {
        $this->savedAddressService->deleteAddress($request->user(), $address);

        return response()->json(null, 204);
    }

    public function makeDefault(int $address, Request $request): SavedAddressData
    {
        return SavedAddressData::from($this->savedAddressService->makeDefault($request->user(), $address))->wrap('data');
    }
}
