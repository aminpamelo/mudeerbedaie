@php
    use Illuminate\Support\Facades\Storage;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $data['certificate_name'] ?? 'Certificate' }}</title>
    <style>
        @import url('https://fonts.bunny.net/css?family=montserrat:400,500,600,700|playfair-display:400,700|lora:400,700|poppins:400,500,600,700|raleway:400,500,600,700|roboto:400,500,700|open-sans:400,600,700|nunito:400,600,700|merriweather:400,700|oswald:400,500,600,700|dancing-script:400,700|amiri:400,700|scheherazade-new:400,700|noto-sans-arabic:400,700');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        .certificate-container {
            position: relative;
            width: {{ $width }}px;
            height: {{ $height }}px;
            background-color: {{ $backgroundColor }};
            overflow: hidden;
        }

        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .element {
            position: absolute;
        }

        .element-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .element-image img {
            max-width: 100%;
            max-height: 100%;
        }

        .element-shape-rectangle {
            border-style: solid;
        }

        .element-shape-circle {
            border-radius: 50%;
            border-style: solid;
        }

        .element-shape-line {
            border-bottom-style: solid;
        }

        .element-qr img {
            max-width: 100%;
            max-height: 100%;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        @if($backgroundImage && Storage::disk('public')->exists($backgroundImage))
            @php
                $bgImageData = base64_encode(Storage::disk('public')->get($backgroundImage));
                $bgImageExt = pathinfo($backgroundImage, PATHINFO_EXTENSION);
                $bgImageMime = match($bgImageExt) {
                    'jpg', 'jpeg' => 'jpeg',
                    'png' => 'png',
                    'gif' => 'gif',
                    default => 'jpeg'
                };
            @endphp
            <img src="data:image/{{ $bgImageMime }};base64,{{ $bgImageData }}" class="background-image" alt="Certificate Background" />
        @endif

        @foreach($elements as $element)
            @php
                $elementType = $element['type'] ?? 'text';
                $x = $element['x'] ?? 0;
                $y = $element['y'] ?? 0;
                $width = $element['width'] ?? 100;
                $height = $element['height'] ?? 50;
                $rotation = $element['rotation'] ?? 0;
                $opacity = $element['opacity'] ?? 1;
            @endphp

            @if($elementType === 'text')
                {{-- Static text element --}}
                <div class="element element-text" style="
                    left: {{ $x }}px;
                    top: {{ $y }}px;
                    width: {{ $width }}px;
                    height: {{ $height }}px;
                    font-size: {{ $element['fontSize'] ?? 16 }}px;
                    font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                    font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                    color: {{ $element['color'] ?? '#000000' }};
                    text-align: {{ $element['textAlign'] ?? 'left' }};
                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                    transform: rotate({{ $rotation }}deg);
                    opacity: {{ $opacity }};
                ">{{ $element['content'] ?? '' }}</div>

            @elseif($elementType === 'dynamic')
                {{-- Dynamic data element --}}
                @php
                    $field = $element['field'] ?? 'student_name';
                    $content = $data[$field] ?? '';
                    $prefix = $element['prefix'] ?? '';
                    $suffix = $element['suffix'] ?? '';
                    $displayContent = $prefix . $content . $suffix;
                @endphp

                <div class="element element-text" style="
                    left: {{ $x }}px;
                    top: {{ $y }}px;
                    width: {{ $width }}px;
                    height: {{ $height }}px;
                    font-size: {{ $element['fontSize'] ?? 16 }}px;
                    font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                    font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                    color: {{ $element['color'] ?? '#000000' }};
                    text-align: {{ $element['textAlign'] ?? 'left' }};
                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                    transform: rotate({{ $rotation }}deg);
                    opacity: {{ $opacity }};
                ">{{ $displayContent }}</div>

            @elseif($elementType === 'image')
                {{-- Image element --}}
                @php
                    $imageSrc = $element['src'] ?? '';
                    $objectFit = $element['objectFit'] ?? 'contain';
                @endphp

                @if($imageSrc && Storage::disk('public')->exists($imageSrc))
                    @php
                        $imageData = base64_encode(Storage::disk('public')->get($imageSrc));
                        $imageExtension = pathinfo($imageSrc, PATHINFO_EXTENSION);
                        $imageMimeType = match($imageExtension) {
                            'jpg', 'jpeg' => 'jpeg',
                            'png' => 'png',
                            'gif' => 'gif',
                            'svg' => 'svg+xml',
                            default => 'png'
                        };
                        $base64Image = "data:image/{$imageMimeType};base64,{$imageData}";
                    @endphp
                    <div class="element element-image" style="
                        left: {{ $x }}px;
                        top: {{ $y }}px;
                        width: {{ $width }}px;
                        height: {{ $height }}px;
                        transform: rotate({{ $rotation }}deg);
                        opacity: {{ $opacity }};
                    ">
                        <img src="{{ $base64Image }}" style="
                            width: 100%;
                            height: 100%;
                            object-fit: {{ $objectFit }};
                        " />
                    </div>
                @endif

            @elseif($elementType === 'shape')
                {{-- Shape element --}}
                @php
                    $shapeType = $element['shape'] ?? 'rectangle';
                    $borderWidth = $element['borderWidth'] ?? 1;
                    $borderColor = $element['borderColor'] ?? '#000000';
                    $borderStyle = $element['borderStyle'] ?? 'solid';
                    $fillColor = $element['fillColor'] ?? 'transparent';
                @endphp

                <div class="element element-shape-{{ $shapeType }}" style="
                    left: {{ $x }}px;
                    top: {{ $y }}px;
                    width: {{ $width }}px;
                    height: {{ $height }}px;
                    border-width: {{ $borderWidth }}px;
                    border-color: {{ $borderColor }};
                    border-style: {{ $borderStyle }};
                    background-color: {{ $fillColor }};
                    transform: rotate({{ $rotation }}deg);
                    opacity: {{ $opacity }};
                "></div>

            @elseif($elementType === 'qr')
                {{-- QR Code element --}}
                @php
                    $qrData = $element['data'] ?? 'verification_url';
                    $qrContent = $qrData === 'verification_url'
                        ? ($data['verification_url'] ?? '')
                        : ($data['certificate_number'] ?? '');

                    // Generate QR code as data URL
                    $qrCodeDataUrl = app(\App\Services\CertificatePdfGenerator::class)->generateQrCodeDataUrl($qrContent);
                @endphp

                @if($qrContent)
                    <div class="element element-qr" style="
                        left: {{ $x }}px;
                        top: {{ $y }}px;
                        width: {{ $element['size'] ?? 80 }}px;
                        height: {{ $element['size'] ?? 80 }}px;
                        opacity: {{ $opacity }};
                    ">
                        <img src="{{ $qrCodeDataUrl }}" style="width: 100%; height: 100%;" />
                    </div>
                @endif
            @endif
        @endforeach
    </div>
</body>
</html>
