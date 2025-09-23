@props([
    'id' => 'pieChart',
    'title' => 'Pie Chart',
    'data' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'],
    'showLegend' => true,
    'legendPosition' => 'right'
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20,
            textStyle: {
                color: '#333',
                fontSize: 16,
                fontWeight: 'bold'
            }
        },
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        @if($showLegend)
        legend: {
            orient: '{{ $legendPosition }}' === 'right' ? 'vertical' : 'horizontal',
            {{ $legendPosition }}: '{{ $legendPosition }}' === 'right' ? 'left' : 'center',
            top: '{{ $legendPosition }}' === 'right' ? 'middle' : 'bottom',
            textStyle: {
                color: '#333',
                fontSize: 12
            }
        },
        @endif
        series: [{
            name: 'Data',
            type: 'pie',
            radius: ['40%', '70%'],
            center: ['{{ $legendPosition }}' === 'right' ? '60%' : '50%', '50%'],
            avoidLabelOverlap: false,
            emphasis: {
                label: {
                    show: true,
                    fontSize: '18',
                    fontWeight: 'bold'
                },
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            },
            label: {
                show: false,
                position: 'center'
            },
            labelLine: {
                show: false
            },
            data: @json($data).map(function(item, index) {
                return {
                    value: item.value,
                    name: item.name,
                    itemStyle: {
                        color: colors[index % colors.length]
                    }
                };
            })
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>