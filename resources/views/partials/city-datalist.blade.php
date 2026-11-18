<datalist id="citySuggestions">
    @foreach (\App\Models\Mill::citySuggestions() as $city)
        <option value="{{ $city }}"></option>
    @endforeach
</datalist>
