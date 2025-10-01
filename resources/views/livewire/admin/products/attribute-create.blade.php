<?php

use App\Models\ProductAttributeTemplate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public $name = '';
    public $label = '';
    public $type = 'text';
    public $values = '';
    public $is_required = false;
    public $description = '';

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_attribute_templates,name',
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,select,color,number',
            'values' => 'required_if:type,select|nullable|string',
            'is_required' => 'boolean',
            'description' => 'nullable|string',
        ];
    }

    public function updatedLabel(): void
    {
        $this->name = Str::slug($this->label, '_');
    }

    public function save(): void
    {
        $this->validate();

        $values = null;
        if ($this->type === 'select' && $this->values) {
            $values = array_filter(array_map('trim', explode(',', $this->values)));
        }

        $attribute = ProductAttributeTemplate::create([
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'values' => $values,
            'is_required' => $this->is_required,
            'description' => $this->description,
        ]);

        session()->flash('success', 'Product attribute created successfully.');

        $this->redirect(route('product-attributes.show', $attribute));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create Product Attribute</flux:heading>
            <flux:text class="mt-2">Define a new attribute for variable products</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('product-attributes.index') }}" icon="arrow-left">
            Back to Attributes
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Basic Information</flux:heading>
                    <flux:text class="mt-1 text-sm">Define the attribute details</flux:text>
                </div>

                <flux:field>
                    <flux:label>Attribute Label</flux:label>
                    <flux:input wire:model.live="label" placeholder="e.g., Color, Size, Material" />
                    <flux:error name="label" />
                    <flux:description>The display name for this attribute</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Attribute Name</flux:label>
                    <flux:input wire:model="name" placeholder="color_size_material" />
                    <flux:error name="name" />
                    <flux:description>Internal name (auto-generated from label)</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Attribute Type</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="text">Text Input</flux:select.option>
                        <flux:select.option value="select">Select Dropdown</flux:select.option>
                        <flux:select.option value="color">Color Picker</flux:select.option>
                        <flux:select.option value="number">Number Input</flux:select.option>
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>
                        <flux:checkbox wire:model="is_required" />
                        Required Attribute
                    </flux:label>
                    <flux:description>Whether this attribute is required for all product variants</flux:description>
                </flux:field>
            </div>

            <!-- Attribute Configuration -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Configuration</flux:heading>
                    <flux:text class="mt-1 text-sm">Configure attribute options and behavior</flux:text>
                </div>

                @if($type === 'select')
                    <flux:field>
                        <flux:label>Attribute Values</flux:label>
                        <flux:textarea wire:model="values" placeholder="Red, Blue, Green, Yellow" rows="4" />
                        <flux:error name="values" />
                        <flux:description>Enter comma-separated values for the dropdown options</flux:description>
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Optional description for this attribute" rows="3" />
                    <flux:error name="description" />
                    <flux:description>Optional description to help with attribute usage</flux:description>
                </flux:field>

                <!-- Attribute Type Information -->
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <flux:heading size="sm" class="mb-2">Attribute Type Guide</flux:heading>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div><strong>Text:</strong> Free text input (e.g., Custom Text)</div>
                        <div><strong>Select:</strong> Dropdown with predefined options (e.g., Size: S, M, L, XL)</div>
                        <div><strong>Color:</strong> Color picker for color selection</div>
                        <div><strong>Number:</strong> Numeric input (e.g., Length, Weight)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <flux:button variant="outline" href="{{ route('product-attributes.index') }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Create Attribute
            </flux:button>
        </div>
    </form>
</div>