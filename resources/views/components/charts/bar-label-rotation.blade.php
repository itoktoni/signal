@props([
    'id' => 'barLabelRotationChart',
    'title' => 'Bar Label Rotation',
    'categories' => [],
    'data' => [],
    'height' => 300,
    'color' => '#5470c6'
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
                type: 'shadow'
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '15%',
            top: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: @json($categories),
            axisLabel: {
                rotate: 45,
                fontSize: 12,
                interval: 0
            },
            axisTick: {
                alignWithLabel: true
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: function(value) {
                    return value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value;
                }
            }
        },
        series: [{
            name: 'Value',
            type: 'bar',
            barWidth: '70%',
            data: @json($data),
            itemStyle: {
                color: '{{ $color }}',
                borderRadius: [2, 2, 0, 0]
            },
            label: {
                show: true,
                position: 'top',
                formatter: function(params) {
                    return params.value >= 1000 ? (params.value / 1000).toFixed(1) + 'k' : params.value;
                },
                fontSize: 11,
                fontWeight: 'bold'
            },
            emphasis: {
                itemStyle: {
                    color: '{{ $color }}DD',
                    shadowBlur: 8,
                    shadowColor: 'rgba(0, 0, 0, 0.3)'
                },
                label: {
                    show: true,
                    fontSize: 12,
                    fontWeight: 'bold'
                }
            }
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>