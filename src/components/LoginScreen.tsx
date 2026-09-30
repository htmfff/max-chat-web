import { useState } from 'react';
import { GreenApiError } from '../api/greenApi';
import type { Credentials } from '../types';

interface LoginScreenProps {
  onLogin: (credentials: Credentials) => Promise<void>;
}

const FIELDS: Array<{ key: keyof Credentials; label: string; placeholder: string; hint: string }> = [
  {
    key: 'idInstance',
    label: 'idInstance',
    placeholder: '1101123456',
    hint: 'Числовой ID инстанса из личного кабинета GREEN-API',
  },
  {
    key: 'apiTokenInstance',
    label: 'apiTokenInstance',
    placeholder: 'd75b3a66374942c5b3c019c698abc20',
    hint: 'API-токен инстанса (раздел «Настройки» в кабинете)',
  },
];

export default function LoginScreen({ onLogin }: LoginScreenProps) {
  const [values, setValues] = useState<Credentials>({ idInstance: '', apiTokenInstance: '' });
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();

    const idInstance = values.idInstance.trim();
    const apiTokenInstance = values.apiTokenInstance.trim();

    if (!idInstance || !apiTokenInstance) {
      setError('Заполните оба поля');
      return;
    }

    setPending(true);
    setError(null);

    try {
      await onLogin({ idInstance, apiTokenInstance });
    } catch (loginError) {
      setError(
        loginError instanceof GreenApiError
          ? loginError.message
          : 'Не удалось подключиться. Проверьте учётные данные.',
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <div className="login">
      <form className="login__card" onSubmit={submit}>
        <div className="login__logo" aria-hidden="true">
          MAX
        </div>
        <h1 className="login__title">Вход в чат MAX</h1>
        <p className="login__subtitle">
          Учётные данные инстанта GREEN-API. Они хранятся только в вашем браузере.
        </p>

        {FIELDS.map((field) => (
          <label className="field" key={field.key}>
            <span className="field__label">{field.label}</span>
            <input
              className="field__input"
              value={values[field.key]}
              placeholder={field.placeholder}
              autoComplete="off"
              spellCheck={false}
              onChange={(event) =>
                setValues((prev) => ({ ...prev, [field.key]: event.target.value }))
              }
            />
            <span className="field__hint">{field.hint}</span>
          </label>
        ))}

        {error ? <p className="login__error">{error}</p> : null}

        <button className="button button--primary login__submit" type="submit" disabled={pending}>
          {pending ? 'Подключаем…' : 'Подключиться'}
        </button>
      </form>
    </div>
  );
}