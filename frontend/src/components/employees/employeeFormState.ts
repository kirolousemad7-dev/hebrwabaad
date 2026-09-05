import type { Employee } from '../../types/api'

export type EmployeeFormState = {
  name: string
  email: string
  role: string
  password: string
  password_confirmation: string
  is_active: boolean
}

export function emptyEmployeeForm(): EmployeeFormState {
  return {
    name: '',
    email: '',
    role: 'WEB_DEVELOPER',
    password: '',
    password_confirmation: '',
    is_active: true,
  }
}

export function formFromEmployee(employee: Employee): EmployeeFormState {
  return {
    name: employee.name,
    email: employee.email,
    role: employee.role,
    password: '',
    password_confirmation: '',
    is_active: employee.is_active,
  }
}
