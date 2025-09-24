@props([
    'id' => 'datasetEncodeChart',
    'title' => 'Dataset Encode',
    'source' => [
        ['product' => 'Matcha Latte', '2015' => 43.3, '2016' => 85.8, '2017' => 93.7],
        ['product' => 'Milk Tea', '2015' => 83.1, '2016' => 73.4, '2017' => 55.1],
        ['product' => 'Cheese Cocoa', '2015' => 86.4, '2016' => 65.2, '2017' => 82.5],
        ['product' => 'Walnut Brownie', '2015' => 72.4, '2016' => 53.9, '2017' => 39.1]
    ],
    'height' => 300
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        legend: {},
        tooltip: {},
        dataset: {
            source: @json($source)
        },
        xAxis: {
            type: 'category',
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {},
        series: [
            {
                type: 'line',
                encode: {
                    x: 'product',
                    y: '2015'
                }
            },
            {
                type: 'line',
                encode: {
                    x: 'product',
                    y: '2016'
                }
            },
            {
                type: 'line',
                encode: {
                    x: 'product',
                    y: '2017'
                }
            }
        ]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>