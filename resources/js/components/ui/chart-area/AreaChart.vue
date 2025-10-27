<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
  type ChartData,
  type ChartOptions
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface DataPoint {
  [key: string]: string | number
}

interface Props {
  data: DataPoint[]
  index: string
  categories: string[]
  colors?: string[]
}

const props = withDefaults(defineProps<Props>(), {
  colors: () => ['#3b82f6', '#10b981']
})

const chartData = computed<ChartData<'line'>>(() => {
  const labels = props.data.map(item => item[props.index] as string)

  const datasets = props.categories.map((category, idx) => ({
    label: category.charAt(0).toUpperCase() + category.slice(1),
    data: props.data.map(item => item[category] as number),
    borderColor: props.colors[idx % props.colors.length],
    backgroundColor: props.colors[idx % props.colors.length] + '33', // 20% opacity
    fill: true,
    tension: 0.4,
    borderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
  }))

  return {
    labels,
    datasets
  }
})

const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      display: true,
      position: 'top',
      align: 'end',
      labels: {
        usePointStyle: true,
        boxWidth: 8,
        boxHeight: 8,
        padding: 15,
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12,
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1,
      titleColor: '#fff',
      bodyColor: '#fff',
      bodySpacing: 4,
      usePointStyle: true,
    }
  },
  scales: {
    x: {
      grid: {
        display: false,
      },
      border: {
        display: false,
      }
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(0, 0, 0, 0.05)',
      },
      border: {
        display: false,
      },
      ticks: {
        precision: 0
      }
    }
  }
}))
</script>

<template>
  <div class="relative h-full w-full">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
