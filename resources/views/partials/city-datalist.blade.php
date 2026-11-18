<datalist id="citySuggestions">
    @foreach (\App\Support\Cities::suggestions() as $city)
        <option value="{{ $city }}"></option>
    @endforeach
</datalist>
