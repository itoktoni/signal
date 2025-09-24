@props([
    'id' => 'candlestickBrushChart',
    'title' => 'Candlestick Brush',
    'categories' => [],
    'data' => [],
    'height' => 300,
    'upColor' => '#ec0000',
    'downColor' => '#00da3c'
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
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross'
            },
            formatter: function (params) {
                var data = params[0].data;
                return [
                    'Date: ' + params[0].name + '<br/>',
                    'Open: ' + data[0] + '<br/>',
                    'Close: ' + data[1] + '<br/>',
                    'Lowest: ' + data[2] + '<br/>',
                    'Highest: ' + data[3] + '<br/>'
                ].join('');
            }
        },
        legend: {
            data: ['Price'],
            top: 50
        },
        grid: {
            left: '10%',
            right: '10%',
            bottom: '15%',
            top: '15%'
        },
        xAxis: {
            type: 'category',
            data: @json($categories),
            scale: true,
            boundaryGap: false,
            axisLine: {onZero: false},
            splitLine: {show: false},
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            scale: true,
            splitArea: {
                show: true
            }
        },
        dataZoom: [{
            type: 'inside',
            start: 50,
            end: 100
        }, {
            show: true,
            type: 'slider',
            top: '90%',
            start: 50,
            end: 100
        }],
        brush: {
            xAxisIndex: 'all',
            brushLink: 'all',
            outOfBrush: {
                colorAlpha: 0.1
            }
        },
        series: [{
            name: 'Price',
            type: 'candlestick',
            data: @json($data),
            itemStyle: {
                color: '{{ $upColor }}',
                color0: '{{ $downColor }}',
                borderColor: '{{ $upColor }}',
                borderColor0: '{{ $downColor }}'
            },
            emphasis: {
                itemStyle: {
                    borderWidth: 2
                }
            }
        }]
    };
    chart.setOption(option);

    // Enable brush
    chart.dispatchAction({
        type: 'takeGlobalCursor',
        key: 'brush',
        brushOption: {
            brushType: 'lineX',
            brushMode: 'single'
        }
    });

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>